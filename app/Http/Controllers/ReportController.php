<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TableFloat;
use App\Models\TableLedger;
use App\Models\GameTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reportType     = $request->input('type', 'ledger');
        $fromDate       = $request->input('from_date', now()->format('Y-m-d'));
        $toDate         = $request->input('to_date', now()->format('Y-m-d'));
        $tableId        = $request->input('table_id');
        $tabId          = $request->input('tab_id');
        $gameday        = $request->input('gameday', now()->format('Y-m-d'));
        $txnType        = $request->input('txn_type');
        $paymentMedium  = $request->input('payment_medium');

        $tables = GameTable::where('status', 1)->get();
        $data   = $this->getReportData($reportType, $fromDate, $toDate, $tableId, $tabId, $gameday, $txnType, $paymentMedium);

        // Distinct tab IDs for the dropdown — only meaningful for ledger + specific table
        $tabIds = [];
        if ($reportType === 'ledger' && $tableId) {
            $tabIds = TableLedger::where('table_id', $tableId)
                ->whereNotNull('tab_id')
                ->where('tab_id', '!=', '')
                ->distinct()
                ->orderBy('tab_id')
                ->pluck('tab_id')
                ->toArray();
        }

        if ($request->has('export')) {
            return $this->exportReport($reportType, $data, $gameday);
        }

        return view('reports.index', compact(
            'tables', 'data', 'reportType',
            'fromDate', 'toDate', 'tableId', 'tabId', 'tabIds', 'gameday',
            'txnType', 'paymentMedium'
        ));
    }

    private function getReportData($type, $from, $to, $tableId = null, $tabId = null, $gameday = null, $txnType = null, $paymentMedium = null)
    {
        return match ($type) {
            'float'   => $this->getFloatReport($from, $to, $tableId),
            'gameday' => $this->getGameDayReport($gameday),
            'ledger'  => $this->getLedgerReport($from, $to, $tableId, $tabId, $txnType, $paymentMedium),
            'table'   => $this->getTableReport(),
            default   => collect(),
        };
    }

    private function getFloatReport($from, $to, $tableId)
    {
        $query = TableFloat::with('gameTable')
            ->whereBetween('gameday', [$from, $to])
            ->orderBy('gameday')
            ->orderBy('float_id');

        if ($tableId) {
            $query->where('table_id', $tableId);
        }

        $floats = $query->get();

        // Pre-fetch ledger aggregates for all relevant (table_id, gameday) pairs,
        // keyed by "table_id|gameday" so we can look them up per float row.
        // Each float session is one open/close period within a gameday; totals
        // are shared across sessions on the same gameday (matching gameday report).
        $tableGamedays = $floats
            ->map(fn($f) => ['table_id' => $f->table_id, 'gameday' => $f->gameday->toDateString()])
            ->unique(fn($r) => $r['table_id'] . '|' . $r['gameday']);

        // Build a flat collection of [table_id, gameday, totals] rows
        $ledgerTotals = collect();
        foreach ($tableGamedays as $pair) {
            $row = DB::table('table_ledgers')
                ->select(
                    DB::raw("SUM(CASE WHEN txn_type = 'FILL'    THEN amount ELSE 0 END) AS total_fills"),
                    DB::raw("SUM(CASE WHEN txn_type = 'CREDIT'  THEN amount ELSE 0 END) AS total_credits"),
                    DB::raw("SUM(CASE WHEN txn_type = 'BUYIN'   THEN amount ELSE 0 END) AS total_buy"),
                    DB::raw("SUM(CASE WHEN txn_type = 'CASHOUT' THEN amount ELSE 0 END) AS total_cashout"),
                )
                ->where('table_id', $pair['table_id'])
                ->where('gameday', $pair['gameday'])
                ->first();

            $ledgerTotals->put(
                $pair['table_id'] . '|' . $pair['gameday'],
                $row
            );
        }

        // Enrich each float row with computed values using the gameday report formula:
        //   openingFloat  = float_open + fills − |credits|
        //   closingFloat  = float_open + fills − |credits| + totalBuy − |cashout|
        //   result        = closingFloat − openingFloat  =  totalBuy − |cashout|
        return $floats->map(function ($f) use ($ledgerTotals) {
            $key    = $f->table_id . '|' . $f->gameday->toDateString();
            $totals = $ledgerTotals->get($key);

            $rawOpen      = (float) $f->float_open;
            $totalFills   = $totals ? (float) $totals->total_fills   : 0;
            $totalCredits = $totals ? (float) $totals->total_credits  : 0;
            $totalBuy     = $totals ? (float) $totals->total_buy      : 0;
            $totalCashout = $totals ? (float) $totals->total_cashout  : 0;

            // Gameday report formula
            $openingFloat  = round($rawOpen + $totalFills - abs($totalCredits), 2);
            $closingFloat  = round($rawOpen + $totalFills - abs($totalCredits) + $totalBuy - abs($totalCashout), 2);
            $result        = round($totalBuy - abs($totalCashout), 2);  // simplification of closingFloat − openingFloat

            $isOpen = is_null($f->closed_at);

            // Attach computed fields directly onto the model instance
            $f->computed_opening_float = $openingFloat;
            $f->computed_closing_float = $isOpen ? null : $closingFloat;  // only meaningful once closed
            $f->computed_result        = $isOpen ? null : $result;
            $f->is_open                = $isOpen;

            return $f;
        });
    }

    private function getGameDayReport($gameday)
    {
        // 1. Load all active game tables with their game type
        $gameTables = GameTable::with('gameType')
            ->where('status', 1)
            ->orderBy('id')
            ->get();

        // 2. Aggregate float records per table for this gameday (sum across multiple open/close sessions)
        //    total_opening_float = SUM of all float_open values (table opened multiple times)
        $floatsByTable = DB::table('table_floats')
            ->select(
                'table_id',
                DB::raw('SUM(float_open) AS total_opening_float'),
                DB::raw('MAX(CASE WHEN closed_at IS NULL THEN 1 ELSE 0 END) AS has_open_session')
            )
            ->where('gameday', $gameday)
            ->groupBy('table_id')
            ->get()
            ->keyBy('table_id');

        // 3. Aggregate ledger entries per (table_id, tab_id) for this gameday
        //    total_buy = ALL BUYIN txns (cash + chips), matching ledger behaviour
        //    total_buy_cash  = BUYIN with payment_medium = CASH (i.e. DROP / cash buy-in)
        //    total_buy_chips = BUYIN with payment_medium = CHIPS
        $ledgerAggregates = DB::table('table_ledgers')
            ->select(
                'table_id',
                'tab_id',
                DB::raw("SUM(CASE WHEN txn_type = 'FILL'   THEN amount ELSE 0 END) AS total_fills"),
                DB::raw("SUM(CASE WHEN txn_type = 'CREDIT' THEN amount ELSE 0 END) AS total_credits"),
                DB::raw("SUM(CASE WHEN txn_type = 'BUYIN'  THEN amount ELSE 0 END) AS total_buy"),
                DB::raw("SUM(CASE WHEN txn_type = 'BUYIN' AND payment_medium = 'CASH'  THEN amount ELSE 0 END) AS total_buy_cash"),
                DB::raw("SUM(CASE WHEN txn_type = 'BUYIN' AND payment_medium = 'CHIPS' THEN amount ELSE 0 END) AS total_buy_chips"),
                DB::raw("SUM(CASE WHEN txn_type = 'CASHOUT' THEN amount ELSE 0 END) AS total_cashout")
            )
            ->where('gameday', $gameday)
            ->whereNotNull('tab_id')
            ->where('tab_id', '!=', '')
            ->groupBy('table_id', 'tab_id')
            ->get()
            ->groupBy('table_id');

        // 4. Aggregate ledger totals at table level for closing float & result calculation
        $ledgerTotalsByTable = DB::table('table_ledgers')
            ->select(
                'table_id',
                DB::raw("SUM(CASE WHEN txn_type = 'FILL'   THEN amount ELSE 0 END) AS total_fills"),
                DB::raw("SUM(CASE WHEN txn_type = 'CREDIT' THEN amount ELSE 0 END) AS total_credits"),
                DB::raw("SUM(CASE WHEN txn_type = 'BUYIN'  THEN amount ELSE 0 END) AS total_buy"),
                DB::raw("SUM(CASE WHEN txn_type = 'CASHOUT' THEN amount ELSE 0 END) AS total_cashout")
            )
            ->where('gameday', $gameday)
            ->groupBy('table_id')
            ->get()
            ->keyBy('table_id');

        // 5. Build the structured result
        $result = collect();

        foreach ($gameTables as $table) {
            $float      = $floatsByTable->get($table->id);
            $ledgerTots = $ledgerTotalsByTable->get($table->id);

            // Only include tables that had activity on this gameday
            $tabRows = $ledgerAggregates->get($table->id, collect())->values();

            // Skip tables with no float and no ledger activity on this day
            if (is_null($float) && $tabRows->isEmpty()) {
                continue;
            }

            
            $totalFills   = $ledgerTots ? (float) $ledgerTots->total_fills   : 0;
            $totalCredits = $ledgerTots ? (float) $ledgerTots->total_credits : 0;
            $totalBuy     = $ledgerTots ? (float) $ledgerTots->total_buy     : 0;
            $totalCashout = $ledgerTots ? (float) $ledgerTots->total_cashout : 0;
            $openingFloat = $float ? (float) $float->total_opening_float + $totalFills - abs($totalCredits) : null;

            // Closing Float = Total Opening + Total Fills - Total Credits + Total Buy-In (cash+chips) + Total Cashout
            // NOTE: cashout amounts are stored as negative values in the DB, so we add (not subtract) here
            $closingFloat = $openingFloat !== null
                ? round($float->total_opening_float + $totalFills - abs($totalCredits) + $totalBuy - abs($totalCashout), 2)
                : null;

            // Result = Closing Float - Opening Float
            $resultFloat = ($closingFloat !== null && $openingFloat !== null)
                ? round($closingFloat - $openingFloat, 2)
                : null;

            $result->push((object) [
                'table_id'       => $table->id,
                'table_name'     => $table->table_name,
                'game_type'      => $table->gameType->name ?? 'N/A',
                'opening_float'  => $openingFloat,
                'closing_float'  => $closingFloat,
                'result'         => $resultFloat,
                'float_status'   => $float
                    ? ($float->has_open_session ? 'Open' : 'Closed')
                    : 'No Float',
                'tabs'           => $tabRows,
            ]);
        }

        return $result;
    }

    private function getLedgerReport($from, $to, $tableId, $tabId = null, $txnType = null, $paymentMedium = null)
    {
        $query = TableLedger::with('gameTable')
            ->whereBetween('gameday', [$from, $to]);

        if ($tableId) {
            $query->where('table_id', $tableId);

            if ($tabId) {
                $query->where('tab_id', $tabId);
            }
        }

        if ($txnType) {
            if ($txnType === 'DROP') {
                // DROP is stored as BUYIN with payment_medium = CASH; no literal 'DROP' in the DB
                $query->where('txn_type', 'BUYIN')->where('payment_medium', 'CASH');
            } else {
                $query->where('txn_type', $txnType);

                if ($paymentMedium && $txnType === 'BUYIN') {
                    $query->where('payment_medium', $paymentMedium);
                }
            }
        }

        return $query->get();
    }

    private function getTableReport()
    {
        return GameTable::with('gameType')->get();
    }

    private function exportReport($type, $data, $gameday = null)
    {
        $filename = "report_{$type}_" . now()->format('Ymd_His') . ".csv";

        $headers = match ($type) {
            'float'   => ['Float ID', 'Table', 'Gameday', 'Status', 'Opening Float', 'Closing Float', 'Result', 'Opened At', 'Closed At'],
            'gameday' => ['Table ID', 'Table Name', 'Game Type', 'Opening Float', 'Closing Float', 'Float Status',
                          'Players', 'Total Fills', 'Total Credits', 'Total Buy-In', 'Total Cash-Out'],
            'ledger'  => ['Txn ID', 'Table', 'Tab ID', 'Type', 'Medium', 'Amount', 'Float Balance', 'Gameday', 'Reference', 'At'],
            'table'   => ['ID', 'Name', 'Game Type', 'MAC Address', 'Status'],
            default   => [],
        };

        $callback = function () use ($type, $data, $headers, $gameday) {
            $file = fopen('php://output', 'w');

            if ($type === 'gameday') {
                fputcsv($file, ['Gaming Day Report - Date: ' . $gameday]);
                fputcsv($file, []);
            }

            fputcsv($file, $headers);

            if ($type === 'gameday') {
                foreach ($data as $block) {
                    if ($block->tabs->isEmpty()) {
                        fputcsv($file, [
                            $block->table_id,
                            $block->table_name,
                            $block->game_type,
                            $block->opening_float ?? 'N/A',
                            $block->closing_float ?? 'Open',
                            $block->float_status,
                            '— No Tab Activity —', '', '', '', '',
                        ]);
                    } else {
                        foreach ($block->tabs as $tab) {
                            fputcsv($file, [
                                $block->table_id,
                                $block->table_name,
                                $block->game_type,
                                $block->opening_float ?? 'N/A',
                                $block->closing_float ?? 'Open',
                                $block->float_status,
                                $tab->tab_id == 0 ? 'Dealer' : 'Player ' . $tab->tab_id,
                                number_format((float) $tab->total_fills, 2),
                                number_format((float) $tab->total_credits, 2),
                                number_format((float) $tab->total_buy, 2),
                                number_format((float) $tab->total_cashout, 2),
                            ]);
                        }
                    }
                    fputcsv($file, []); 
                }
            } else {
                foreach ($data as $row) {
                    $line = match ($type) {
                        'float' => [
                            $row->float_id,
                            $row->gameTable->table_name ?? 'N/A',
                            $row->gameday->format('Y-m-d'),
                            $row->is_open ? 'Open' : 'Closed',
                            number_format($row->computed_opening_float, 2),
                            $row->computed_closing_float !== null ? number_format($row->computed_closing_float, 2) : 'Open',
                            $row->computed_result !== null ? number_format($row->computed_result, 2) : '—',
                            $row->opened_at->format('Y-m-d H:i'),
                            $row->closed_at ? $row->closed_at->format('Y-m-d H:i') : 'N/A',
                        ],
                        'ledger' => [
                            $row->txn_id,
                            $row->gameTable->table_name ?? 'N/A',
                            $row->tab_id ?? '—',
                            $row->txn_type,
                            $row->payment_medium ?? '—',
                            $row->amount,
                            $row->float_balance,
                            $row->gameday->format('Y-m-d'),
                            $row->reference,
                            $row->created_at,
                        ],
                        'table' => [
                            $row->id,
                            $row->table_name,
                            $row->gameType->name ?? 'N/A',
                            $row->active_mac ?? 'N/A',
                            $row->status ? 'Active' : 'Inactive',
                        ],
                        default => [],
                    };
                    fputcsv($file, $line);
                }
            }

            fclose($file);
        };

        return Response::stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ]);
    }
}
