<?php

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\TabSession;
use App\Models\TableLedger;
use App\Models\GameDay;
use App\Services\TabStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TabSessionController — cage operations (Buyin & Cashout).
 *
 * This is the core cage module embedded in the Dashboard.
 * Cashier processes Buyin → OTP issued → session starts.
 * Cashier processes Cashout → credits redeemed → session closes.
 *
 * ALL writes are wrapped in DB::transaction() with tab_statuses
 * updated atomically to guarantee failure recovery at every step.
 */
class TabSessionController extends Controller
{
    public function __construct(private TabStatusService $statusService) {}

    // ── Buyin (Cash-In) ───────────────────────────────────────────

    /**
     * Process a Buyin: credit the tab, generate OTP, open a session.
     *
     * POST /api/v1/tabs/{id}/buyin
     * Header: Idempotency-Key: <uuid>   ← required for duplicate safety
     *
     * Body:
     *   amount    float   Cash amount from player
     *   reference string  Cage slip / receipt number
     *   gameday   date    Current game day (YYYY-MM-DD)
     */
    public function buyin(Request $request, int $id)
    {
        $tab = GameTable::with(['tabStatus', 'gameType'])->findOrFail($id);

        // Guards
        if (!$tab->isEnabled()) {
            return response()->json(['success' => false, 'message' => 'Tab is disabled.'], 422);
        }
        if ($tab->isLocked()) {
            return response()->json(['success' => false, 'message' => 'Tab is locked. Unlock before processing a buyin.'], 422);
        }
        if ($tab->hasActiveSession()) {
            return response()->json(['success' => false, 'message' => 'Tab already has an active session. Process a Cashout first.'], 422);
        }

        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
            'gameday'   => 'required|date_format:Y-m-d',
        ]);

        $result = null;

        DB::transaction(function () use ($tab, $data, $request, &$result) {
            $amount = (float) $data['amount'];

            // 1. Generate unique OTP
            $otp = TabSession::generateOtp();

            // 2. Create the session record
            $session = TabSession::create([
                'table_id'        => $tab->id,
                'otp'             => $otp,
                'buyin_amount'    => $amount,
                'opening_balance' => $amount,
                'opened_by'       => auth()->guard('web')->id() ?? auth()->guard('sanctum')->id(),
                'status'          => 'active',
                'opened_at'       => now(),
                'reference'       => $data['reference'] ?? null,
            ]);

            // 3. Record ledger entry (BUYIN)
            TableLedger::create([
                'table_id'       => $tab->id,
                'session_id'     => $session->session_id,
                'otp'            => $otp,
                'txn_type'       => TableLedger::BUYIN,
                'payment_medium' => 'CASH',
                'amount'         => $amount,
                'tab_balance'    => $amount,
                'float_balance'  => 0,
                'gameday'        => $data['gameday'],
                'processed'      => 1,
                'reference'      => $data['reference'] ?? null,
                'initiated_by'   => auth()->guard('web')->id() ?? auth()->guard('sanctum')->id(),
            ]);

            // 4. Update tab_statuses ATOMICALLY — failure recovery baseline
            $this->statusService->onBuyin($tab, $session, $amount);

            $result = [
                'session_id'  => $session->session_id,
                'otp'         => $otp,
                'tab_id'      => $tab->id,
                'tab_name'    => $tab->table_name,
                'game_type'   => $tab->gameType?->code,
                'amount'      => $amount,
                'balance'     => $amount,
                'opened_at'   => $session->opened_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Buyin processed. Session started.',
            'data'    => $result,
        ], 201);
    }

    // ── Cashout (Cash-Out) ────────────────────────────────────────

    /**
     * Process a Cashout: zero the tab balance, close the session.
     *
     * POST /api/v1/tabs/{id}/cashout
     * Header: Idempotency-Key: <uuid>   ← required for duplicate safety
     *
     * Body:
     *   amount    float   Amount being paid to player (must match current_balance)
     *   otp       string  OTP to verify correct session
     *   reference string  Cage receipt
     *   gameday   date    Current game day
     */
    public function cashout(Request $request, int $id)
    {
        $tab = GameTable::with(['tabStatus', 'activeSession'])->findOrFail($id);

        if (!$tab->hasActiveSession()) {
            return response()->json(['success' => false, 'message' => 'No active session on this tab.'], 422);
        }

        $data = $request->validate([
            'amount'    => 'required|numeric|min:0',
            'otp'       => 'required|string|size:6',
            'reference' => 'nullable|string|max:100',
            'gameday'   => 'required|date_format:Y-m-d',
        ]);

        $session = $tab->activeSession;

        // OTP verification
        if ($session->otp !== $data['otp']) {
            return response()->json(['success' => false, 'message' => 'OTP mismatch. Cashout rejected.'], 422);
        }

        $result = null;

        DB::transaction(function () use ($tab, $session, $data, &$result) {
            $amount = (float) $data['amount'];

            // 1. Close the session
            $session->update([
                'status'          => 'completed',
                'cashout_amount'  => $amount,
                'closing_balance' => $amount,
                'closed_by'       => auth()->guard('web')->id() ?? auth()->guard('sanctum')->id(),
                'closed_at'       => now(),
            ]);

            // 2. Record ledger entry (CASHOUT)
            TableLedger::create([
                'table_id'       => $tab->id,
                'session_id'     => $session->session_id,
                'otp'            => $session->otp,
                'txn_type'       => TableLedger::CASHOUT,
                'payment_medium' => 'CASH',
                'amount'         => $amount,
                'tab_balance'    => 0,
                'float_balance'  => 0,
                'gameday'        => $data['gameday'],
                'processed'      => 1,
                'reference'      => $data['reference'] ?? null,
                'initiated_by'   => auth()->guard('web')->id() ?? auth()->guard('sanctum')->id(),
            ]);

            // 3. Update tab_statuses ATOMICALLY — reset to idle
            $this->statusService->onCashout($tab, $session, $amount);

            $result = [
                'session_id'  => $session->session_id,
                'otp'         => $session->otp,
                'tab_id'      => $tab->id,
                'tab_name'    => $tab->table_name,
                'buyin'       => (float) $session->buyin_amount,
                'cashout'     => $amount,
                'net_result'  => (float) $session->buyin_amount - $amount,
                'closed_at'   => now()->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Cashout processed. Session closed.',
            'data'    => $result,
        ]);
    }

    // ── OTP Verify ────────────────────────────────────────────────

    /**
     * Verify an OTP against the active session on a tab.
     * Used by terminal on startup to confirm the correct player.
     *
     * POST /api/v1/tabs/{id}/verify-otp
     * Body: { "otp": "482931" }
     */
    public function verifyOtp(Request $request, int $id)
    {
        $tab = GameTable::with(['tabStatus', 'activeSession'])->findOrFail($id);

        $data = $request->validate(['otp' => 'required|string|size:6']);

        if (!$tab->hasActiveSession()) {
            return response()->json(['success' => false, 'message' => 'No active session.'], 404);
        }

        if ($tab->activeSession->otp !== $data['otp']) {
            return response()->json(['success' => false, 'message' => 'OTP invalid.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified.',
            'session' => [
                'session_id' => $tab->activeSession->session_id,
                'balance'    => (float) $tab->tabStatus?->current_balance,
                'started_at' => $tab->activeSession->opened_at?->toIso8601String(),
            ],
        ]);
    }

    // ── Session History ───────────────────────────────────────────

    /**
     * Get paginated session history for a tab.
     * GET /api/v1/tabs/{id}/sessions?per_page=20&status=completed
     */
    public function sessions(Request $request, int $id)
    {
        $tab = GameTable::findOrFail($id);

        $sessions = TabSession::forTab($tab->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('session_id', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json($sessions);
    }
}
