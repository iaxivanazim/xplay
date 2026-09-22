<?php

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\GameType;
use App\Models\PayoutRule;
use App\Models\GameTablePayoutRule;
use App\Models\GameTableConfig;
use App\Models\TabStatus;
use App\Services\TabStatusService;
use App\Rules\PipeSeparatedNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TabController — manages the Tabs Configuration module.
 *
 * Two responsibilities:
 *   1. CRUD for tabs (add/edit/delete tab configuration)
 *   2. Tab state management (enable/disable, lock/unlock/break, MAC register)
 *
 * All tabs of the same game_type share the same default payout rules.
 * No Chips module — denomination is a decimal on the tab itself.
 */
class TabController extends Controller
{
    public function __construct(private TabStatusService $statusService) {}

    // ── Index ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $gameTypeFilter = $request->input('game_type');
        $statusFilter   = $request->input('status', 'all'); // all | active | inactive

        $query = GameTable::with(['gameType', 'tabStatus', 'activeSession', 'config.preset'])
            ->orderBy('table_name');

        if ($gameTypeFilter) {
            $query->whereHas('gameType', fn($q) => $q->where('code', $gameTypeFilter));
        }

        if ($statusFilter === 'active') {
            $query->where('status', 1);
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', 0);
        }

        $tabs      = $query->get();
        $gameTypes = GameType::where('status', 1)->get();

        // Aggregate counts for header
        $totalCount  = $tabs->count();
        $activeCount = $tabs->filter(fn($t) => $t->tabStatus?->hasActiveSession())->count();

        return view('tabs.index', compact('tabs', 'gameTypes', 'totalCount', 'activeCount', 'gameTypeFilter', 'statusFilter'));
    }

    // ── Create / Store ────────────────────────────────────────────

    public function create()
    {
        $gameTypes = GameType::where('status', 1)->get();
        return view('tabs.create', compact('gameTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'table_name'    => 'required|string|max:100|unique:game_tables,table_name',
            'game_type_id'  => 'required|exists:game_types,id',
            'active_mac'    => 'nullable|string|max:17|regex:/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/',
            'denomination'  => 'nullable|numeric|min:0.0001',
            'bet_index'     => 'nullable|integer|min:1|max:9',
        ]);

        DB::transaction(function () use ($request) {
            $tab = GameTable::create([
                'table_name'   => $request->table_name,
                'game_type_id' => $request->game_type_id,
                'active_mac'   => $request->active_mac ?: null,
                'denomination' => $request->denomination ?? 1.0,
                'bet_index'    => $request->bet_index ?? 1,
                'float'        => 0,
                'status'       => true,
                'lock_status'  => 'unlocked',
            ]);

            // Apply default payout rules for this game type
            $this->applyDefaultPayoutRules($tab);

            // Initialise tab_statuses row (failure recovery baseline)
            $this->statusService->initialise($tab);
        });

        return redirect()->route('tabs.index')
            ->with('success', 'Tab configured successfully.');
    }

    // ── Edit / Update ─────────────────────────────────────────────

    public function edit(GameTable $tab)
    {
        $tab->load(['gameType', 'tabStatus', 'activeSession', 'config.preset', 'payoutRules.payoutRule']);
        $gameTypes   = GameType::where('status', 1)->get();
        $payoutRules = PayoutRule::where('game_type_id', $tab->game_type_id)->get()->map(function ($rule) use ($tab) {
            $saved = GameTablePayoutRule::where('table_id', $tab->id)
                ->where('payout_id', $rule->payout_id)->first();
            $rule->is_active = $saved ? $saved->is_active : $rule->is_active;
            return $rule;
        });

        return view('tabs.edit', compact('tab', 'gameTypes', 'payoutRules'));
    }

    public function update(Request $request, GameTable $tab)
    {
        // Block edit if session is active
        if ($tab->hasActiveSession()) {
            return back()->with('error', "Tab '{$tab->table_name}' has an active player session. End the session before editing.");
        }

        $request->validate([
            'table_name'   => 'required|string|max:100|unique:game_tables,table_name,' . $tab->id,
            'denomination' => 'nullable|numeric|min:0.0001',
            'bet_index'    => 'nullable|integer|min:1|max:9',
        ]);

        DB::transaction(function () use ($request, $tab) {
            $tab->update([
                'table_name'  => $request->table_name,
                'denomination'=> $request->denomination ?? $tab->denomination,
                'bet_index'   => $request->bet_index ?? $tab->bet_index,
            ]);

            // Sync payout rule overrides if submitted
            if ($request->has('payout_overrides')) {
                $this->syncPayoutRules($tab, $request->input('payout_overrides', []));
            }
        });

        return redirect()->route('tabs.index')
            ->with('success', "Tab '{$tab->table_name}' updated.");
    }

    // ── Enable / Disable ──────────────────────────────────────────

    public function enable(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot enable: tab has an active session.');
        }

        DB::transaction(function () use ($tab) {
            $tab->update(['status' => true]);
            $this->statusService->onStatusChange($tab, true);
        });

        return back()->with('success', "Tab '{$tab->table_name}' enabled.");
    }

    public function disable(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot disable: tab has an active session. Process a Cashout first.');
        }

        DB::transaction(function () use ($tab) {
            $tab->update(['status' => false]);
            $this->statusService->onStatusChange($tab, false);
        });

        return back()->with('success', "Tab '{$tab->table_name}' disabled.");
    }

    // ── Lock / Unlock / Break ─────────────────────────────────────

    public function lock(Request $request, GameTable $tab)
    {
        $lockStatus = $request->input('lock_status', 'locked'); // locked | break
        if (!in_array($lockStatus, ['locked', 'break'])) {
            return back()->with('error', 'Invalid lock status.');
        }

        DB::transaction(function () use ($tab, $lockStatus) {
            $tab->update([
                'lock_status' => $lockStatus,
                'locked_at'   => now(),
                'locked_by'   => auth()->id(),
            ]);
            $this->statusService->onLockChange($tab, $lockStatus);
        });

        $label = $lockStatus === 'break' ? 'put on break' : 'locked';
        return back()->with('success', "Tab '{$tab->table_name}' {$label}.");
    }

    public function unlock(GameTable $tab)
    {
        DB::transaction(function () use ($tab) {
            $tab->update([
                'lock_status' => 'unlocked',
                'locked_at'   => null,
                'locked_by'   => null,
            ]);
            $this->statusService->onLockChange($tab, 'unlocked');
        });

        return back()->with('success', "Tab '{$tab->table_name}' unlocked.");
    }

    // ── MAC Address Management ────────────────────────────────────

    public function registerMac(Request $request, GameTable $tab)
    {
        $request->validate([
            'mac_address' => [
                'required', 'string',
                'regex:/^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$/',
            ],
        ]);

        $mac = strtoupper($request->mac_address);

        // Check MAC not already bound to another tab
        $conflict = GameTable::where('active_mac', $mac)
            ->where('id', '!=', $tab->id)->first();

        if ($conflict) {
            return back()->with('error', "MAC {$mac} is already bound to tab '{$conflict->table_name}'.");
        }

        DB::transaction(function () use ($tab, $mac) {
            $tab->update(['active_mac' => $mac]);
            $this->statusService->onMacChange($tab, $mac);
        });

        return back()->with('success', "MAC {$mac} registered to tab '{$tab->table_name}'.");
    }

    public function unregisterMac(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot unregister MAC while a session is active.');
        }

        DB::transaction(function () use ($tab) {
            $tab->update(['active_mac' => null]);
            $this->statusService->onMacChange($tab, null);
        });

        return back()->with('success', "MAC unregistered from tab '{$tab->table_name}'.");
    }

    // ── Delete ────────────────────────────────────────────────────

    public function destroy(GameTable $tab)
    {
        if ($tab->hasActiveSession()) {
            return back()->with('error', 'Cannot delete a tab with an active session.');
        }

        $name = $tab->table_name;
        $tab->delete();

        return redirect()->route('tabs.index')
            ->with('success', "Tab '{$name}' deleted.");
    }

    // ── Private Helpers ───────────────────────────────────────────

    /**
     * Apply all default payout rules for the tab's game type.
     * All rules start as active (cashier can override per-tab via edit).
     */
    private function applyDefaultPayoutRules(GameTable $tab): void
    {
        $rules = PayoutRule::where('game_type_id', $tab->game_type_id)->get();

        $data = $rules->map(fn($rule) => [
            'table_id'   => $tab->id,
            'payout_id'  => $rule->payout_id,
            'is_active'  => $rule->is_active,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (!empty($data)) {
            GameTablePayoutRule::insert($data);
        }
    }

    /**
     * Sync payout rule active/inactive overrides for a tab.
     */
    private function syncPayoutRules(GameTable $tab, array $overrides): void
    {
        $allRules = PayoutRule::where('game_type_id', $tab->game_type_id)->pluck('payout_id');

        foreach ($allRules as $payoutId) {
            GameTablePayoutRule::updateOrCreate(
                ['table_id' => $tab->id, 'payout_id' => $payoutId],
                ['is_active' => isset($overrides[$payoutId]) ? 1 : 0]
            );
        }
    }
}
