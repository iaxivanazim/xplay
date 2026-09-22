<?php

namespace App\Http\Controllers;

use App\Models\GameTable;
use App\Models\TableFloat;
use App\Models\TableLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Render the dashboard view.
     */
    public function index()
    {
        return view('dashboard');
    }

    /**
     * Live dashboard data endpoint (polled every N seconds).
     * GET /dashboard/live-data
     */
    public function liveData()
    {
        $today = today()->toDateString();

        // ── Active tables with open float ─────────────────────────────────────
        $tables = GameTable::with([
            'gameType',
            'currentFloat',
        ])
            ->where('status', 1)
            ->orderByRaw('
                CASE WHEN EXISTS (
                    SELECT 1 FROM table_floats
                    WHERE table_floats.table_id = game_tables.id
                      AND table_floats.closed_at IS NULL
                ) THEN 0 ELSE 1 END ASC
            ')
            ->get();

        // Collect all gamedates for currently-open floats (may include past dates)
        $openGamedays = $tables
            ->map(fn($t) => $t->currentFloat?->gameday?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Fall back to today if no open tables exist
        $globalGamedays = count($openGamedays) ? $openGamedays : [$today];

        $tableData = $tables->map(function ($table) use ($today) {
            $float = $table->currentFloat;
            $isOpen = !is_null($float);

            // Use the gameday from the table's own open float, not today's date
            $gameday = $isOpen ? $float->gameday->toDateString() : $today;

            // Ledger summary scoped to the current float session only (dashboard-only behaviour).
            // For open tables: restrict to transactions created at or after the current float's
            // opened_at so that only the LATEST session's data is shown when a table is
            // opened, closed, and re-opened multiple times within the same gameday.
            // For closed tables: fall back to all transactions on the gameday (no open float).
            $txnQuery = TableLedger::where('table_id', $table->id)
                ->where('gameday', $gameday);

            if ($isOpen && $float->opened_at) {
                $txnQuery->where('created_at', '>=', $float->opened_at);
            }

            $txns = $txnQuery->get();

            $totalBuyin      = (float) $txns->where('txn_type', 'BUYIN')->sum('amount');                          // all buy-ins
            $totalBuyinChips = (float) $txns->where('txn_type', 'BUYIN')->where('payment_medium', 'CHIPS')->sum('amount'); // chips buy-ins only
            $totalBuyinCash  = (float) $txns->where('txn_type', 'BUYIN')->where('payment_medium', 'CASH')->sum('amount');  // cash buy-ins (Drop)
            $totalCashout    = (float) $txns->where('txn_type', 'CASHOUT')->sum('amount');
            $totalFill       = (float) $txns->where('txn_type', 'FILL')->sum('amount');
            $totalCredit     = (float) $txns->where('txn_type', 'CREDIT')->sum('amount');
            $totalDrop       = (float) $txns->where('txn_type', 'DROP')->sum('amount');
            $totalPayout     = (float) $txns->where('txn_type', 'PAYOUT')->sum('amount');
            $totalLoss       = (float) $txns->where('txn_type', 'LOSS')->sum('amount');  // losing chips swept into tray
            $txnCount        = $txns->count();

            // Float formula mirrors calculateFloatBalance() in TableLedgerController:
            //   + chip_buyins  : chips go onto the float when player buys in with chips
            //   + fills        : chips added from vault
            //   + credits      : signed; negative credit = chips removed from table
            //   + losses       : losing chips swept from betting circle into float tray
            //   - cashouts     : chips leave table (abs() handles any sign inconsistency)
            // PAYOUT and cash buyins (DROP) do NOT affect the float display.
            $openingFloat = $isOpen ? (float) ($float->float_open ?? 0) : 0;
            $liveFloat    = round($openingFloat + $totalBuyinChips + $totalFill + $totalCredit + $totalLoss - abs($totalCashout), 2);

            // Use the recalculated value — do NOT read float_balance from DB,
            // as stored values may include cash buyins erroneously.
            $currentFloat = $isOpen ? $liveFloat : null;
            $statFloat    = $liveFloat;

            // Recent transactions (last 5) for activity feed
            $recentTxns = $txns->sortByDesc('txn_id')->take(5)->map(fn($t) => [
                'txn_id'       => $t->txn_id,
                'txn_type'     => $t->txn_type,
                // BUYIN+CASH displayed as DROP everywhere
                'display_type' => ($t->txn_type === 'BUYIN' && $t->payment_medium === 'CASH') ? 'DROP' : $t->txn_type,
                'amount'       => (float) $t->amount,
                'tab_id'       => $t->tab_id,
                'at'           => $t->created_at?->format('H:i:s'),
            ])->values();

            // Build 6 player seats mapped by tab_id number directly to seat position.
            // tab_id is numeric (1–6); each player sits at the seat matching their tab number,
            // so tab_id 3 always occupies seat 3 regardless of transaction order.
            $activeTabMap = $txns->whereNotNull('tab_id')
                ->pluck('tab_id')
                ->unique()
                ->mapWithKeys(fn($tab) => [(int) $tab => $tab]); // key by numeric tab_id

            $players = collect(range(1, 6))->map(function ($seat) use ($activeTabMap, $txns) {
                $tab = $activeTabMap->get($seat); // seat == tab_id number
                if (!$tab) {
                    return ['seat' => $seat, 'active' => false, 'tab_id' => null, 'balance' => null, 'last_action' => null];
                }
                $tabTxns    = $txns->where('tab_id', $tab)->sortByDesc('txn_id');
                $lastTabTxn = $tabTxns->first();
                return [
                    'seat'        => $seat,
                    'active'      => true,
                    'tab_id'      => $tab,
                    'balance'     => $lastTabTxn ? (float) $lastTabTxn->tab_balance : 0,
                    'last_action' => $lastTabTxn ? $lastTabTxn->txn_type : null,
                    'last_amount' => $lastTabTxn ? (float) $lastTabTxn->amount : null,
                    'last_at'     => $lastTabTxn?->created_at?->format('H:i:s'),
                ];
            });

            // Net revenue mirrors the Gameday Report result exactly.
            //
            // Gameday Report derives:
            //   openingFloat  = float_open + fills − |credits|
            //   closingFloat  = float_open + fills − |credits| + totalBuy − |cashout|
            //   result        = closingFloat − openingFloat
            //                 = totalBuy − |cashout|          ← all other terms cancel
            //
            // For open sessions we apply the same cancellation live.
            // For closed sessions the stored float_close already incorporates those terms,
            // so we fall back to float_close − float_open.
            $fOpen  = $isOpen ? (float) ($float->float_open  ?? 0) : null;
            $fClose = $isOpen ? ((float) ($float->float_close ?? 0) ?: null) : null;
            if ($isOpen) {
                // Live result = totalBuy(cash+chips) − |totalCashout|
                $netRevenue = round($totalBuyin - abs($totalCashout), 2);
            } elseif ($fClose !== null && $fOpen !== null) {
                // Closed session: use the stored closing float
                $netRevenue = round($fClose - $fOpen, 2);
            } else {
                $netRevenue = null;
            }

            return [
                'id'              => $table->id,
                'name'            => $table->table_name,
                'game_type'       => $table->gameType?->name ?? 'Unknown',
                'game_code'       => $table->gameType?->code ?? '??',
                'felt_color'      => $table->felt_color ?? '#1a5c2e',
                'is_open'         => $isOpen,
                'float_open'      => $isOpen ? (float) ($float->float_open ?? 0) : null,
                'float_current'   => $currentFloat,
                'opened_at'       => $isOpen ? $float->opened_at?->format('H:i') : null,
                'txn_count'       => $txnCount,
                // ── Stats bar fields ──────────────────────────────────────────
                'stat_float'      => $isOpen ? round($statFloat, 2) : null,    // opening + chip buyins + fills - credits
                'total_buyin'     => round($totalBuyin, 2),                      // cash + chips buy-ins combined
                'total_drop'      => round($totalBuyinCash, 2),                // cash buy-ins only
                // ── Other financials ─────────────────────────────────────────
                'total_cashout'   => $totalCashout,
                'total_fill'      => $totalFill,
                'total_credit'    => $totalCredit,
                'total_payout'    => $totalPayout,
                'total_loss'      => $totalLoss,
                'net_revenue'     => $netRevenue,  // closing float − opening float; null while session is open
                'recent_txns'     => $recentTxns,
                'players'         => $players,
                'active_players'  => $activeTabMap->count(),
            ];
        });

        // ── Global KPIs ────────────────────────────────────────────────────────
        $openTables    = $tables->filter(fn($t) => !is_null($t->currentFloat))->count();
        $allTxnsToday  = TableLedger::whereIn('gameday', $globalGamedays)->get();
        $totalFloat    = $tableData->sum('float_current');
        // Live net_revenue: open sessions only (current float scoped).
        $totalRevenue  = $tableData->filter(fn($t) => $t['is_open'] && $t['net_revenue'] !== null)->sum('net_revenue');
        $totalTxns     = $allTxnsToday->count();
        $totalBuyins   = (float) $allTxnsToday->where('txn_type', 'BUYIN')->sum('amount');

        // ── Gameday totals (all float sessions, including previously closed ones) ──
        // These mirror how total_buyins already covers the entire gameday.
        $allFloatsToday  = TableFloat::whereIn('gameday', $globalGamedays)->get();
        $closedToday     = $allFloatsToday->filter(fn($f) => !is_null($f->closed_at));

        // Day total float = closing balance of all closed sessions + live balance of open sessions
        $totalFloatDay   = round((float) $closedToday->sum('float_close') + (float) $totalFloat, 2);

        // Day net revenue = (float_close − float_open) for every closed session
        //                 + live net revenue for currently open sessions
        $closedRevDay    = (float) $closedToday->sum(fn($f) => ((float) ($f->float_close ?? 0)) - (float) $f->float_open);
        $openRevDay      = (float) $tableData->filter(fn($t) => $t['is_open'] && $t['net_revenue'] !== null)->sum('net_revenue');
        $totalRevenueDay = round($closedRevDay + $openRevDay, 2);

        // Hourly transaction volume (last 12 hours)
        $hourlyVolume = TableLedger::whereIn('gameday', $globalGamedays)
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as volume'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour')
            ->map(fn($r) => ['count' => $r->count, 'volume' => (float) $r->volume]);

        return response()->json([
            'success'    => true,
            'timestamp'  => now()->toISOString(),
            'gameday'    => count($globalGamedays) === 1 ? $globalGamedays[0] : $globalGamedays,
            'kpis'       => [
                'open_tables'       => $openTables,
                'total_tables'      => $tables->count(),
                'total_float'       => round($totalFloat, 2),      // live: sum of current open session floats
                'total_float_day'   => $totalFloatDay,             // day: all closed closing floats + live
                'total_revenue'     => round($totalRevenue, 2),    // live: open sessions net P&L
                'total_revenue_day' => $totalRevenueDay,           // day: all sessions net P&L
                'total_txns'        => $totalTxns,
                'total_buyins'      => round($totalBuyins, 2),
            ],
            'hourly_volume' => $hourlyVolume,
            'tables'     => $tableData->values(),
        ]);
    }
}
