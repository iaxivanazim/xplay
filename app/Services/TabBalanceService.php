<?php

namespace App\Services;

use App\Models\Tab;
use App\Models\TabBalanceLog;
use App\Models\TabSession;
use App\Models\TabStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TabBalanceService
{
    public const OP_BUYIN       = 'BUYIN';
    public const OP_CASHOUT     = 'CASHOUT';
    public const OP_WIN         = 'WIN';
    public const OP_LOSS        = 'LOSS';
    public const OP_BET         = 'BET';
    public const OP_ADJUST      = 'ADJUST';

    public const VALID_OPS = [
        self::OP_BUYIN,
        self::OP_CASHOUT,
        self::OP_WIN,
        self::OP_LOSS,
        self::OP_BET,
        self::OP_ADJUST,
    ];

    /**
     * Apply a balance changing operation to a tab.
     *
     * @param Tab $tab The tab instance
     * @param string $type Operation type (BUYIN, CASHOUT, WIN, LOSS, BET, ADJUST)
     * @param float $amount The transaction amount
     * @param string|null $reference Transaction / Game reference (e.g. game_no or ledger ref)
     * @param string|null $notes Descriptive notes
     * @param array $metadata Additional metadata (game_type, winning_number, etc.)
     * @return array Resulting balance and operation data
     */
    public function applyOperation(
        Tab $tab,
        string $type,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,
        array $metadata = []
    ): array {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::VALID_OPS, true)) {
            throw new InvalidArgumentException("Invalid balance operation type: {$type}");
        }

        $amount = round(abs($amount), 2);

        return DB::transaction(function () use ($tab, $type, $amount, $reference, $notes, $metadata) {
            // Lock or create status record
            $status = TabStatus::where('tab_id', $tab->id)->lockForUpdate()->first();
            if (!$status) {
                $status = TabStatus::create([
                    'tab_id'            => $tab->id,
                    'status'            => 'idle',
                    'lock_status'       => 'unlocked',
                    'current_balance'   => 0.00,
                    'balance_version'   => 0,
                    'connection_status' => 'online',
                ]);
            }

            $prevBalance = (float) $status->current_balance;

            // Calculate new balance
            $newBalance = match ($type) {
                self::OP_BUYIN  => round($prevBalance + $amount, 2),
                self::OP_CASHOUT => 0.00, // Cashout resets balance to zero
                self::OP_WIN    => round($prevBalance + $amount, 2),
                self::OP_LOSS   => max(0.00, round($prevBalance - $amount, 2)),
                self::OP_BET    => max(0.00, round($prevBalance - $amount, 2)),
                self::OP_ADJUST => max(0.00, round($prevBalance + ($metadata['signed_amount'] ?? $amount), 2)),
                default         => $prevBalance,
            };

            $now = now();
            $newVersion = ((int) ($status->balance_version ?? 0)) + 1;

            // Update TabStatus
            $status->current_balance      = $newBalance;
            $status->balance_version      = $newVersion;
            $status->last_op_type         = $type;
            $status->last_op_amount       = $amount;
            $status->last_op_prev_balance = $prevBalance;
            $status->last_op_new_balance  = $newBalance;
            $status->last_op_ref          = $reference;
            $status->last_op_notes        = $notes;
            $status->last_op_at           = $now;
            $status->last_synced_at       = $now;

            // Shift aggregates
            if ($type === self::OP_BUYIN) {
                $status->shift_total_in = round((float)$status->shift_total_in + $amount, 2);
                $status->session_buyin  = round((float)$status->session_buyin + $amount, 2);
            } elseif ($type === self::OP_CASHOUT) {
                $status->shift_total_out = round((float)$status->shift_total_out + $amount, 2);
                $status->session_cashout = round((float)$status->session_cashout + $amount, 2);
            } elseif ($type === self::OP_WIN) {
                $status->shift_total_win = round((float)$status->shift_total_win + $amount, 2);
                $status->last_win_amount = $amount;
            } elseif ($type === self::OP_LOSS) {
                $status->shift_total_bet = round((float)$status->shift_total_bet + $amount, 2);
                $status->last_bet_amount = $amount;
            }

            if (!empty($metadata['game_no'])) {
                $status->last_game_no = $metadata['game_no'];
                $status->last_game_at = $now;
            }

            // Update failure recovery JSON snapshot
            $recData = is_array($status->recovery_data) ? $status->recovery_data : [];
            $recData['tab_id']          = $tab->id;
            $recData['tab_name']        = $tab->table_name;
            $recData['balance']         = $newBalance;
            $recData['balance_version'] = $newVersion;
            $recData['last_operation']  = [
                'type'             => $type,
                'amount'           => $amount,
                'previous_balance' => $prevBalance,
                'new_balance'      => $newBalance,
                'reference'        => $reference,
                'notes'            => $notes,
                'timestamp'        => $now->toIso8601String(),
            ];
            $recData['updated_at']      = $now->toIso8601String();
            $status->recovery_data      = $recData;

            $status->save();

            // Update active session if present
            $sessionId = $status->current_session_id;
            if ($sessionId) {
                $session = TabSession::find($sessionId);
                if ($session) {
                    if ($type === self::OP_BUYIN) {
                        $session->increment('buyin_amount', $amount);
                    } elseif ($type === self::OP_CASHOUT) {
                        $session->update([
                            'cashout_amount'  => $amount,
                            'closing_balance' => 0.00,
                            'status'          => 'completed',
                            'closed_at'       => $now,
                        ]);
                    } elseif ($type === self::OP_WIN) {
                        $session->total_win       = round((float)$session->total_win + $amount, 2);
                        $session->closing_balance = $newBalance;
                        $session->save();
                    } elseif ($type === self::OP_LOSS) {
                        $session->total_bet       = round((float)$session->total_bet + $amount, 2);
                        $session->closing_balance = $newBalance;
                        $session->save();
                    }
                }
            }

            // Record audit log entry
            $log = TabBalanceLog::create([
                'tab_id'          => $tab->id,
                'session_id'      => $sessionId,
                'balance_version' => $newVersion,
                'op_type'         => $type,
                'amount'          => $amount,
                'prev_balance'    => $prevBalance,
                'new_balance'     => $newBalance,
                'reference'       => $reference,
                'notes'           => $notes,
                'metadata'        => $metadata,
            ]);

            return [
                'tab_id'           => $tab->id,
                'tab_number'       => $tab->tab_number,
                'previous_balance' => $prevBalance,
                'current_balance'  => $newBalance,
                'balance_version'  => $newVersion,
                'log_id'           => $log->id,
                'operation'        => [
                    'type'             => $type,
                    'amount'           => $amount,
                    'previous_balance' => $prevBalance,
                    'new_balance'      => $newBalance,
                    'reference'        => $reference,
                    'notes'            => $notes,
                    'timestamp'        => $now->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Apply game round resulting (evaluating bet and win to determine WIN or LOSS).
     */
    public function applyGameResult(
        Tab $tab,
        float $betAmount,
        float $winAmount,
        ?string $gameNo = null,
        ?string $gameType = null,
        array $metadata = []
    ): array {
        $betAmount = round(abs($betAmount), 2);
        $winAmount = round(abs($winAmount), 2);

        $metadata['game_no']   = $gameNo;
        $metadata['game_type'] = $gameType;
        $metadata['bet_amount'] = $betAmount;
        $metadata['win_amount'] = $winAmount;

        return DB::transaction(function () use ($tab, $betAmount, $winAmount, $gameNo, $gameType, $metadata) {
            $status = TabStatus::where('tab_id', $tab->id)->lockForUpdate()->first();
            if (!$status) {
                $status = TabStatus::create([
                    'tab_id'            => $tab->id,
                    'status'            => 'idle',
                    'lock_status'       => 'unlocked',
                    'current_balance'   => 0.00,
                    'balance_version'   => 0,
                    'connection_status' => 'online',
                ]);
            }

            $prevBalance = (float) $status->current_balance;

            // Determine if net result is WIN or LOSS
            // Standard casino ETG resulting:
            // Net balance change = -betAmount + winAmount
            $netChange = $winAmount - $betAmount;
            $newBalance = max(0.00, round($prevBalance - $betAmount + $winAmount, 2));

            $type = ($winAmount > 0) ? self::OP_WIN : self::OP_LOSS;
            $opAmount = ($winAmount > 0) ? $winAmount : $betAmount;
            $notes = ($winAmount > 0)
                ? "Game round win on {$gameType}" . ($gameNo ? " (#{$gameNo})" : '')
                : "Game round loss on {$gameType}" . ($gameNo ? " (#{$gameNo})" : '');

            $now = now();
            $newVersion = ((int) ($status->balance_version ?? 0)) + 1;

            $status->current_balance      = $newBalance;
            $status->balance_version      = $newVersion;
            $status->last_op_type         = $type;
            $status->last_op_amount       = $opAmount;
            $status->last_op_prev_balance = $prevBalance;
            $status->last_op_new_balance  = $newBalance;
            $status->last_op_ref          = $gameNo;
            $status->last_op_notes        = $notes;
            $status->last_op_at           = $now;
            $status->last_synced_at       = $now;

            $status->shift_total_bet   = round((float)$status->shift_total_bet + $betAmount, 2);
            $status->shift_total_win   = round((float)$status->shift_total_win + $winAmount, 2);
            $status->shift_games_count = ((int)$status->shift_games_count) + 1;
            $status->shift_last_bet    = $betAmount;
            $status->last_bet_amount   = $betAmount;
            $status->last_win_amount   = $winAmount;
            $status->last_game_no      = $gameNo;
            $status->last_game_at      = $now;

            // Recovery data snapshot
            $recData = is_array($status->recovery_data) ? $status->recovery_data : [];
            $recData['tab_id']          = $tab->id;
            $recData['tab_name']        = $tab->table_name;
            $recData['balance']         = $newBalance;
            $recData['balance_version'] = $newVersion;
            $recData['last_game_no']    = $gameNo;
            $recData['last_operation']  = [
                'type'             => $type,
                'amount'           => $opAmount,
                'net_change'       => $netChange,
                'bet_amount'       => $betAmount,
                'win_amount'       => $winAmount,
                'previous_balance' => $prevBalance,
                'new_balance'      => $newBalance,
                'reference'        => $gameNo,
                'notes'            => $notes,
                'timestamp'        => $now->toIso8601String(),
            ];
            $status->recovery_data = $recData;
            $status->save();

            // Update session
            $sessionId = $status->current_session_id;
            if ($sessionId) {
                $session = TabSession::find($sessionId);
                if ($session) {
                    $session->total_bet       = round((float)$session->total_bet + $betAmount, 2);
                    $session->total_win       = round((float)$session->total_win + $winAmount, 2);
                    $session->games_played    = ((int)$session->games_played) + 1;
                    $session->closing_balance = $newBalance;
                    $session->save();
                }
            }

            // Log entry
            $log = TabBalanceLog::create([
                'tab_id'          => $tab->id,
                'session_id'      => $sessionId,
                'balance_version' => $newVersion,
                'op_type'         => $type,
                'amount'          => $opAmount,
                'prev_balance'    => $prevBalance,
                'new_balance'     => $newBalance,
                'reference'       => $gameNo,
                'notes'           => $notes,
                'metadata'        => $metadata,
            ]);

            return [
                'tab_id'           => $tab->id,
                'tab_number'       => $tab->tab_number,
                'previous_balance' => $prevBalance,
                'current_balance'  => $newBalance,
                'balance_version'  => $newVersion,
                'log_id'           => $log->id,
                'operation'        => [
                    'type'             => $type,
                    'amount'           => $opAmount,
                    'net_change'       => $netChange,
                    'bet_amount'       => $betAmount,
                    'win_amount'       => $winAmount,
                    'previous_balance' => $prevBalance,
                    'new_balance'      => $newBalance,
                    'reference'        => $gameNo,
                    'notes'            => $notes,
                    'timestamp'        => $now->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * Format the last operation payload for heartbeat and status responses.
     */
    public function formatLastOperation(?TabStatus $status): ?array
    {
        if (!$status || empty($status->last_op_type)) {
            return null;
        }

        return [
            'type'             => $status->last_op_type,
            'amount'           => (float) $status->last_op_amount,
            'previous_balance' => (float) $status->last_op_prev_balance,
            'new_balance'      => (float) $status->last_op_new_balance,
            'reference'        => $status->last_op_ref,
            'notes'            => $status->last_op_notes,
            'timestamp'        => $status->last_op_at ? $status->last_op_at->toIso8601String() : null,
        ];
    }

    /**
     * Get recent balance logs for a tab.
     */
    public function getRecentLogs(Tab $tab, int $limit = 10, ?int $sinceVersion = null): array
    {
        $query = TabBalanceLog::where('tab_id', $tab->id)->latest('id');

        if ($sinceVersion !== null) {
            $query->where('balance_version', '>', $sinceVersion);
        }

        return $query->take($limit)->get()->map(function ($log) {
            return [
                'id'              => $log->id,
                'balance_version' => $log->balance_version,
                'op_type'         => $log->op_type,
                'amount'          => (float) $log->amount,
                'prev_balance'    => (float) $log->prev_balance,
                'new_balance'     => (float) $log->new_balance,
                'reference'       => $log->reference,
                'notes'           => $log->notes,
                'metadata'        => $log->metadata,
                'created_at'      => $log->created_at?->toIso8601String(),
            ];
        })->values()->toArray();
    }
}
