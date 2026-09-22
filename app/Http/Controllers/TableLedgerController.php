<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\TableLedger;
use App\Models\GameTable;
use App\Models\TableFloat;

class TableLedgerController extends Controller
{
    // ════════════════════════════════════════════════════════════════
    // WEB: DISPLAY LEDGER INDEX
    // GET /ledger
    // ════════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = TableLedger::with('gameTable');

        // Filters
        // DROP is represented as BUYIN with payment_medium=CASH
        if ($request->filled('txn_type')) {
            if ($request->txn_type === 'DROP') {
                $query->where('txn_type', 'BUYIN')->where('payment_medium', 'CASH');
            } else {
                $query->where('txn_type', $request->txn_type);
            }
        }

        if ($request->filled('payment_medium') && $request->txn_type !== 'DROP') {
            $query->where('payment_medium', $request->payment_medium);
        }

        if ($request->filled('gameday')) {
            $query->where('gameday', $request->gameday);
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        if ($request->filled('processed')) {
            $query->where('processed', $request->processed);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('tab_id', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('initiated_by', 'like', "%{$search}%")
                  ->orWhere('txn_id', $search);
            });
        }

        // Sorting
        $sortOrder = $request->input('sort', 'desc');
        $query->orderBy('txn_id', $sortOrder);

        $ledgers = $query->paginate(25)->withQueryString();
        $tables = GameTable::all();
        // DROP is no longer a separate txn_type — it is BUYIN(CASH). Keep in list for filter UI only.
        $txnTypes = ['FILL','CREDIT','DROP','ADJUST','CASHOUT','BUYIN','PAYOUT','VOID','BET','LOSS'];

        return view('ledger.index', compact('ledgers', 'tables', 'txnTypes'));
    }

    // ════════════════════════════════════════════════════════════════
    // POST NEW TRANSACTION
    // POST /api/v1/ledger/txn
    // ════════════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $request->validate([
            'table_id'       => 'required|exists:game_tables,id',
            // DROP is no longer a valid txn_type; cash buy-ins are stored as BUYIN with payment_medium=CASH
            'txn_type'       => 'required|in:FILL,CREDIT,ADJUST,CASHOUT,BUYIN,PAYOUT,VOID,BET,LOSS',
            'payment_medium' => [
                'nullable',
                'in:CASH,CHIPS',
                function ($attribute, $value, $fail) use ($request) {
                    $type = $request->txn_type;
                    if ($type === 'BUYIN' && empty($value)) {
                        $fail('Payment medium (CASH or CHIPS) is required for BUYIN transactions.');
                    }
                    if ($type !== 'BUYIN' && !empty($value)) {
                        $fail('Payment medium is only applicable for BUYIN transactions.');
                    }
                },
            ],
            'amount'         => 'required|numeric',
            'gameday'        => 'required|date_format:Y-m-d',
            'tab_id'         => 'nullable|string|max:255',
            'reference'      => 'nullable|string|max:255',
            'initiated_by'   => 'nullable|string|max:255',
        ]);

        // ── Per-table atomic lock ──────────────────────────────────────────────
        // Ensures only ONE request at a time progresses through the balance
        // calculation + INSERT for a given table.  Simultaneous duplicates will
        // wait up to 5 s, then receive a 429 rather than silently double-writing.
        $lockKey = 'ledger_txn_lock:table_' . $request->table_id;
        $lock    = Cache::lock($lockKey, 10); // holds for max 10 s (auto-release)

        if (!$lock->block(5)) {             // wait up to 5 s to acquire
            return response()->json([
                'success' => false,
                'message' => 'Table is busy processing another transaction. Please retry in a moment.',
            ], 429);
        }

        try {
            return DB::transaction(function () use ($request, $lock) {

                $table   = GameTable::findOrFail($request->table_id);
                $gameday = $request->gameday;

                // 1. Validate open session exists for this gameday
                $session = TableFloat::where('table_id', $request->table_id)
                                     ->where('gameday', $gameday)
                                     ->where('status', 1) // 1=open
                                     ->first();

                if (!$session) {
                    $lock->release();
                    return response()->json([
                        'success' => false,
                        'message' => "No open session for table '{$table->table_name}' on gameday {$gameday}. Open the table first.",
                    ], 422);
                }

                // ── Duplicate-detection window (5 s) ──────────────────────────
                // Inside the lock, check whether the most recent ledger entry for
                // this table is byte-for-byte identical AND was created within the
                // last 5 seconds.  This catches the case where the consumer retries
                // without an Idempotency-Key and the first request already committed.
                $lastTxn = TableLedger::where('table_id', $request->table_id)
                                      ->where('gameday', $gameday)
                                      ->latest('txn_id')
                                      ->first();

                if ($lastTxn &&
                    $lastTxn->created_at->diffInSeconds(now()) <= 5 &&
                    $lastTxn->txn_type        === $request->txn_type &&
                    (float) $lastTxn->amount  === (float) $request->amount &&
                    $lastTxn->tab_id          === $request->tab_id &&
                    $lastTxn->payment_medium  === ($request->txn_type === 'BUYIN' ? $request->payment_medium : null)
                ) {
                    $lock->release();
                    return response()->json([
                        'success'    => false,
                        'message'    => 'Duplicate transaction detected. An identical transaction was recorded within the last 5 seconds.',
                        'duplicate_of' => $lastTxn->txn_id,
                        'txn'        => $this->formatTxn($lastTxn),
                    ], 409);
                }

                // 2. Get current float balance
                //    (last txn's float_balance, or float_open if no txns yet)
                $currentFloat = $lastTxn
                    ? (float) $lastTxn->float_balance
                    : (float) $session->float_open;

                // 3. Calculate new float balance based on txn_type
                // For BUYIN: only CHIPS affect the float; CASH (DROP) goes to drop box
                $newFloat = $this->calculateFloatBalance(
                    $currentFloat,
                    $request->txn_type,
                    (float) $request->amount,
                    $request->payment_medium
                );

                // 4. Calculate tab balance (per tab_id per gameday)
                $tabBalance = $this->calculateTabBalance(
                    $request->tab_id,
                    $request->table_id,
                    $gameday,
                    $request->txn_type,
                    (float) $request->amount
                );

                // 5. Store transaction
                $txn = TableLedger::create([
                    'table_id'       => $request->table_id,
                    'tab_id'         => $request->tab_id,
                    'txn_type'       => $request->txn_type,
                    'payment_medium' => $request->txn_type === 'BUYIN' ? $request->payment_medium : null,
                    'amount'         => $request->amount,
                    'tab_balance'    => $tabBalance,
                    'float_balance'  => $newFloat,
                    'gameday'        => $gameday,
                    'processed'      => 0,
                    'reference'      => $request->reference,
                    'initiated_by'   => $request->initiated_by,
                ]);

                $lock->release();

                return response()->json([
                    'success' => true,
                    'message' => "{$request->txn_type} transaction recorded successfully.",
                    'txn'     => $this->formatTxn($txn),
                    'balances' => [
                        'float_before' => $currentFloat,
                        'float_after'  => $newFloat,
                        'tab_balance'  => $tabBalance,
                    ]
                ], 201);
            });

        } catch (\Exception $e) {
            $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'Transaction failed.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // GET TRANSACTIONS BY TABLE
    // GET /api/v1/ledger/table/{table_id}?gameday=2026-03-13&txn_type=BUYIN
    // ════════════════════════════════════════════════════════════════
    public function byTable(Request $request, $tableId)
    {
        try {
            $table = GameTable::findOrFail($tableId);

            $query = TableLedger::where('table_id', $tableId)
                                ->latest('txn_id');

            if ($request->gameday) {
                $query->where('gameday', $request->gameday);
            }
            if ($request->txn_type) {
                // DROP filter → BUYIN with CASH medium
                if ($request->txn_type === 'DROP') {
                    $query->where('txn_type', 'BUYIN')->where('payment_medium', 'CASH');
                } else {
                    $query->where('txn_type', $request->txn_type);
                }
            }
            if ($request->tab_id) {
                $query->where('tab_id', $request->tab_id);
            }
            if ($request->processed !== null) {
                $query->where('processed', $request->processed);
            }

            $txns = $query->paginate(50);

            return response()->json([
                'success'    => true,
                'table_id'   => $table->id,
                'table_name' => $table->table_name,
                'total'      => $txns->total(),
                'data'       => $txns->map(fn($t) => $this->formatTxn($t)),
                'pagination' => [
                    'current_page' => $txns->currentPage(),
                    'last_page'    => $txns->lastPage(),
                    'per_page'     => $txns->perPage(),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Table with ID {$tableId} not found.",
            ], 404);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // GAMEDAY SUMMARY FOR TABLE
    // GET /api/v1/ledger/table/{table_id}/summary?gameday=2026-03-13
    // ════════════════════════════════════════════════════════════════
    public function summary(Request $request, $tableId)
    {
        $request->validate(['gameday' => 'required|date_format:Y-m-d']);

        try {
            $table   = GameTable::findOrFail($tableId);
            $gameday = $request->gameday;

            $session = TableFloat::where('table_id', $tableId)
                                  ->where('gameday', $gameday)
                                  ->latest('float_id')
                                  ->first();

            $txns = TableLedger::where('table_id', $tableId)
                                ->where('gameday', $gameday)
                                ->get();

            // Totals per txn_type
            $totals = $txns->groupBy('txn_type')->map(fn($group) => [
                'count'  => $group->count(),
                'total'  => (float) $group->sum('amount'),
            ]);

            // Formula breakdown
            $floatOpen  = (float) ($session?->float_open  ?? 0);
            $floatClose = (float) ($session?->float_close ?? 0);
            $fill       = (float) ($totals['FILL']['total']   ?? 0);
            $credit     = (float) ($totals['CREDIT']['total'] ?? 0);
            $adjust     = (float) ($totals['ADJUST']['total'] ?? 0);
            $cashout    = (float) ($totals['CASHOUT']['total']?? 0);
            $buyin      = (float) ($totals['BUYIN']['total']  ?? 0);
            $payout     = (float) ($totals['PAYOUT']['total'] ?? 0);
            $void       = (float) ($totals['VOID']['total']   ?? 0);
            $bet        = (float) ($totals['BET']['total']    ?? 0);
            $loss       = (float) ($totals['LOSS']['total']   ?? 0);
            // DROP = BUYIN with payment_medium CASH
            $drop = (float) $txns->where('txn_type', 'BUYIN')->where('payment_medium', 'CASH')->sum('amount');

            // Float formula: open + buyin(chips) + fills + credits + adjusts + losses(swept in) - cashouts
            $expectedClose = $floatOpen + $buyin - $cashout + $fill + $credit + $adjust + $loss;
            $variance      = $floatClose > 0 ? round($floatClose - $expectedClose, 2) : null;

            // Last float balance
            $lastTxn       = $txns->sortByDesc('txn_id')->first();
            $currentFloat  = $lastTxn ? (float) $lastTxn->float_balance : $floatOpen;

            return response()->json([
                'success'    => true,
                'table_id'   => $table->id,
                'table_name' => $table->table_name,
                'gameday'    => $gameday,
                'session'    => $session ? [
                    'status'     => $session->status === 1 ? 'open' : 'closed',
                    'float_open' => $floatOpen,
                    'float_close'=> $floatClose ?: null,
                    'opened_by'  => $session->opened_by,
                    'closed_by'  => $session->closed_by,
                    'opened_at'  => $session->opened_at?->toISOString(),
                    'closed_at'  => $session->closed_at?->toISOString(),
                ] : null,
                'float' => [
                    'open'           => $floatOpen,
                    'current'        => $currentFloat,
                    'expected_close' => round($expectedClose, 2),
                    'actual_close'   => $floatClose ?: null,
                    'variance'       => $variance,
                ],
                'breakdown' => [
                    'FILL'    => ['count' => $totals['FILL']['count']   ?? 0, 'total' => $fill],
                    'CREDIT'  => ['count' => $totals['CREDIT']['count'] ?? 0, 'total' => $credit],
                    // DROP = BUYIN(CASH) — count/total derived from BUYIN filtered by payment_medium
                    'DROP'    => ['count' => $txns->where('txn_type','BUYIN')->where('payment_medium','CASH')->count(), 'total' => $drop],
                    'ADJUST'  => ['count' => $totals['ADJUST']['count'] ?? 0, 'total' => $adjust],
                    'CASHOUT' => ['count' => $totals['CASHOUT']['count']?? 0, 'total' => $cashout],
                    'BUYIN'   => ['count' => $totals['BUYIN']['count']  ?? 0, 'total' => $buyin],
                    'PAYOUT'  => ['count' => $totals['PAYOUT']['count'] ?? 0, 'total' => $payout],
                    'VOID'    => ['count' => $totals['VOID']['count']   ?? 0, 'total' => $void],
                    'BET'     => ['count' => $totals['BET']['count']    ?? 0, 'total' => $bet],
                    'LOSS'    => ['count' => $totals['LOSS']['count']   ?? 0, 'total' => $loss],
                ],
                'total_txns' => $txns->count(),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Table with ID {$tableId} not found.",
            ], 404);
        }
    }

    // ════════════════════════════════════════════════════════════════
    // GET TRANSACTIONS BY TAB
    // GET /api/v1/ledger/tab/{tab_id}?gameday=2026-03-13
    // ════════════════════════════════════════════════════════════════
    public function byTab(Request $request, $tabId)
    {
        $query = TableLedger::where('tab_id', $tabId)->latest('txn_id');

        if ($request->gameday) {
            $query->where('gameday', $request->gameday);
        }
        if ($request->table_id) {
            $query->where('table_id', $request->table_id);
        }

        $txns = $query->get();

        return response()->json([
            'success'     => true,
            'tab_id'      => $tabId,
            'total_txns'  => $txns->count(),
            'tab_summary' => [
                'total_buyin'   => (float) $txns->where('txn_type', 'BUYIN')->sum('amount'),
                'total_cashout' => (float) $txns->where('txn_type', 'CASHOUT')->sum('amount'),
                'total_payout'  => (float) $txns->where('txn_type', 'PAYOUT')->sum('amount'),
                'net_balance'   => (float) $txns->last()?->tab_balance ?? 0,
            ],
            'data' => $txns->map(fn($t) => $this->formatTxn($t))->values(),
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════
    // GET SINGLE TRANSACTION
    // GET /api/v1/ledger/txn/{txn_id}
    // ════════════════════════════════════════════════════════════════
    public function show($txnId)
    {
        $txn = TableLedger::find($txnId);

        if (!$txn) {
            return response()->json([
                'success' => false,
                'message' => "Transaction {$txnId} not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'txn'     => $this->formatTxn($txn),
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════
    // CLAIM TRANSACTION (middleware picks it up)
    // POST /api/v1/ledger/txn/{txn_id}/claim
    // ════════════════════════════════════════════════════════════════
    public function claim($txnId)
    {
        $txn = TableLedger::find($txnId);

        if (!$txn) {
            return response()->json(['success' => false, 'message' => "Transaction {$txnId} not found."], 404);
        }

        if ($txn->processed !== 0) {
            return response()->json([
                'success'   => false,
                'message'   => "Transaction {$txnId} is already in state: {$this->processedLabel($txn->processed)}.",
                'processed' => $txn->processed,
            ], 422);
        }

        $txn->update(['processed' => 2]);

        return response()->json([
            'success' => true,
            'message' => "Transaction {$txnId} claimed by middleware.",
            'txn'     => $this->formatTxn($txn->fresh()),
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════
    // COMPLETE TRANSACTION
    // POST /api/v1/ledger/txn/{txn_id}/complete
    // ════════════════════════════════════════════════════════════════
    public function complete($txnId)
    {
        $txn = TableLedger::find($txnId);

        if (!$txn) {
            return response()->json(['success' => false, 'message' => "Transaction {$txnId} not found."], 404);
        }

        if ($txn->processed === 1) {
            return response()->json([
                'success' => false,
                'message' => "Transaction {$txnId} is already complete.",
            ], 422);
        }

        $txn->update(['processed' => 1]);

        return response()->json([
            'success' => true,
            'message' => "Transaction {$txnId} marked as complete.",
            'txn'     => $this->formatTxn($txn->fresh()),
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════
    // GET ALL PENDING TRANSACTIONS (middleware polling)
    // GET /api/v1/ledger/pending?table_id=3
    // ════════════════════════════════════════════════════════════════
    public function pending(Request $request)
    {
        $query = TableLedger::where('processed', 0)->latest('txn_id');

        if ($request->table_id) {
            $query->where('table_id', $request->table_id);
        }

        $txns = $query->get();

        return response()->json([
            'success' => true,
            'count'   => $txns->count(),
            'data'    => $txns->map(fn($t) => $this->formatTxn($t))->values(),
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════
    private function calculateFloatBalance(float $current, string $type, float $amount, ?string $paymentMedium = null): float
    {
        return match($type) {
            'FILL'    =>  $current + $amount,               // chips added to table from vault
            'CREDIT'  =>  $current + $amount,               // amount is signed: negative = deduction from float
            'ADJUST'  =>  $current + $amount,               // +/- signed adjustment
            'CASHOUT' =>  $current - abs($amount),          // player takes cash out
            'BUYIN'   =>  $paymentMedium === 'CASH'         // DROP = cash goes in drop box, NOT float
                            ? $current                      //   cash buy-in: float unchanged
                            : $current + $amount,           //   chip buy-in: chips go on float
            'PAYOUT'  =>  $current,                         // accounting only — tab credited, float unchanged
            'LOSS'    =>  $current + abs($amount),          // losing chips swept from betting circle into tray
            'VOID'    =>  $current,                         // handled by opposite txn
            'BET'     =>  $current,                         // betting circle chips are not in float tray
            default   =>  $current,
        };
    }

    private function calculateTabBalance(?string $tabId, int $tableId, string $gameday, string $type, float $amount): float
    {
        if (!$tabId) return 0;

        // Get last tab balance for this tab on this table/gameday
        $lastTabTxn = TableLedger::where('tab_id', $tabId)
                                  ->where('table_id', $tableId)
                                  ->where('gameday', $gameday)
                                  ->latest('txn_id')
                                  ->first();

        $currentTab = $lastTabTxn ? (float) $lastTabTxn->tab_balance : 0;

        return match($type) {
            'BUYIN'   => $currentTab + $amount,             // player adds credit to tab
            'CASHOUT' => $currentTab - abs($amount),        // player withdraws from tab
            'PAYOUT'  => $currentTab + $amount,             // winnings credited to tab
            'CREDIT'  => $currentTab + $amount,             // credit added to tab
            'BET'     => $currentTab - abs($amount),        // stake leaves player's tab when bet is placed
            'LOSS'    => $currentTab,                       // tab already reduced at BET time; no further change
            'VOID'    => $currentTab,                       // void doesn't affect tab (handled by opposite txn)
            default   => $currentTab,                       // FILL, DROP, ADJUST don't affect tab
        };
    }

    private function formatTxn(TableLedger $txn): array
    {
        // BUYIN with payment_medium=CASH is displayed as DROP
        $displayType = ($txn->txn_type === 'BUYIN' && $txn->payment_medium === 'CASH')
            ? 'DROP'
            : $txn->txn_type;

        return [
            'txn_id'          => $txn->txn_id,
            'table_id'        => $txn->table_id,
            'tab_id'          => $txn->tab_id,
            'txn_type'        => $txn->txn_type,        // raw stored value
            'display_type'    => $displayType,          // DROP for BUYIN+CASH, else same as txn_type
            'payment_medium'  => $txn->payment_medium,
            'amount'          => (float) $txn->amount,
            'tab_balance'     => (float) $txn->tab_balance,
            'float_balance'   => (float) $txn->float_balance,
            'gameday'         => $txn->gameday->toDateString(),
            'processed'       => $txn->processed,
            'processed_label' => $this->processedLabel($txn->processed),
            'reference'       => $txn->reference,
            'initiated_by'    => $txn->initiated_by,
            'created_at'      => $txn->created_at?->toISOString(),
        ];
    }

    private function processedLabel(int $status): string
    {
        return match($status) {
            0 => 'new',
            2 => 'claimed',
            1 => 'complete',
            default => 'unknown',
        };
    }
}
