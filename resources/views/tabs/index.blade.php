<x-app-layout>
    {{-- Custom Casino Terminal Styling --}}
    <style>
        .cage-header-bar {
            background: #0f1d2d;
            border-bottom: 2px solid #1a324b;
            padding: 10px 18px;
            font-size: 12px;
            color: #94a3b8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .cage-kpi-val {
            font-weight: 700;
            color: #ffffff;
            font-family: monospace;
            font-size: 13px;
        }
        .cage-credits {
            color: #f59e0b !important;
            font-weight: 800;
        }
        .cage-btn-subtle {
            background: #172a3d;
            color: #e2e8f0;
            border: 1px solid #2a4365;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .cage-btn-subtle:hover {
            background: #1e3a5f;
            color: #ffffff;
        }
        .cage-btn-exit {
            background: #b91c1c;
            color: #ffffff;
            border: 1px solid #ef4444;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 16px;
            border-radius: 4px;
        }
        .cage-btn-exit:hover {
            background: #dc2626;
            color: #fff;
        }
        .jackpot-card {
            background: #0f1d2d;
            border: 1px solid #1f364d;
            border-radius: 6px;
            padding: 14px;
        }
        .tab-list-table {
            background: #0d1724;
            border: 1px solid #1c2e42;
            color: #cbd5e1;
            font-size: 12px;
            font-family: 'Segoe UI', sans-serif;
        }
        .tab-list-table thead th {
            background: #132235;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.04em;
            border-bottom: 2px solid #233c58;
            padding: 9px 8px;
            white-space: nowrap;
        }
        .tab-list-table tbody td {
            padding: 8px 8px;
            border-bottom: 1px solid #162638;
            vertical-align: middle;
            font-family: monospace;
            font-size: 12px;
        }
        .tab-list-table tbody tr:hover {
            background: #15273c;
        }
        .badge-u {
            background: #065f46;
            color: #34d399;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .badge-l {
            background: #7f1d1d;
            color: #f87171;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .badge-y {
            color: #34d399;
            font-weight: 700;
        }
        .badge-n {
            color: #f87171;
            font-weight: 700;
        }
        .val-positive {
            color: #34d399;
            font-weight: 700;
        }
        .val-negative {
            color: #f87171;
            font-weight: 700;
        }
    </style>

    {{-- TOP KPI BAR --}}
    <div class="cage-header-bar mb-3 rounded-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-circle p-2 bg-primary text-white fw-bold" style="font-size:10px;">STA</span>
                <div>
                    <div>User: <strong class="text-white">{{ auth()->user()->name ?? 'Cashier' }}</strong></div>
                    <div>Period: <span class="text-white-50">{{ now()->format('d-m-Y') }}</span></div>
                    <div>Time: <span class="text-white-50">{{ now()->format('H:i') }}</span></div>
                </div>
            </div>

            <div>
                <div>Total in: <span class="cage-kpi-val">{{ number_format($totalIn, 2) }}</span></div>
                <div>Total out: <span class="cage-kpi-val">{{ number_format($totalOut, 2) }}</span></div>
                <div>Difference: <span class="cage-kpi-val {{ $difference >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($difference, 2) }}</span></div>
            </div>

            <div>
                <div>Total bet: <span class="cage-kpi-val">{{ number_format($totalBet, 2) }}</span></div>
                <div>Total win: <span class="cage-kpi-val">{{ number_format($totalWin, 2) }}</span></div>
                <div>Difference: <span class="cage-kpi-val {{ ($totalBet - $totalWin) >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totalBet - $totalWin, 2) }}</span></div>
            </div>

            <div>
                <div>FT in: <span class="cage-kpi-val">{{ number_format($ftIn, 2) }}</span></div>
                <div>FT out: <span class="cage-kpi-val">{{ number_format($ftOut, 2) }}</span></div>
                <div>Difference: <span class="cage-kpi-val text-danger">{{ number_format($ftIn - $ftOut, 2) }}</span></div>
            </div>

            <div>
                <div>Bill in: <span class="cage-kpi-val">{{ number_format($billIn, 2) }}</span></div>
                <div>Hand pay: <span class="cage-kpi-val">0.00</span></div>
                <div>Credits: <span class="cage-credits">{{ number_format($credits, 2) }}</span></div>
            </div>

            <div>
                <div>Opening Float: <span class="cage-kpi-val">0.00</span></div>
                <div>Float Balance: <span class="cage-kpi-val">0.00</span></div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn cage-btn-subtle" onclick="alert('Day & Shift session active');">
                    Day & Shift
                </button>
                <a href="{{ route('dashboard') }}" class="btn cage-btn-subtle" title="Switch to Grid View">
                    <i class="bi bi-grid-fill me-1"></i>View (Grid)
                </a>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn cage-btn-subtle">Logout</button>
                </form>
                <a href="{{ route('dashboard') }}" class="btn cage-btn-exit">Exit</a>
            </div>
        </div>
    </div>

    {{-- Session Flash Notifications --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 mb-3" style="background:#0f2e1a; color:#6fcf97;">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 mb-3" style="background:#2e1010; color:#eb5757;">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">
        {{-- Left Column: Jackpots Panel --}}
        <div class="col-lg-2">
            <div class="jackpot-card mb-3">
                <div class="text-uppercase fw-bold text-secondary small mb-3" style="letter-spacing:0.08em;">
                    <i class="bi bi-trophy-fill text-warning me-1"></i>JACKPOTS
                </div>
                <div class="mb-3">
                    <div class="text-secondary small">Megapot</div>
                    <div class="fs-5 fw-bold text-warning font-monospace">$9,943.82</div>
                </div>
                <div class="mb-2">
                    <div class="text-secondary small">TOURNAMENT</div>
                    <div class="fs-6 fw-bold text-warning font-monospace">$300.00</div>
                </div>
            </div>

            <div class="jackpot-card mb-3">
                <div class="text-uppercase fw-bold text-secondary small mb-2">Actions</div>
                <a href="{{ route('tabs.create') }}" class="btn btn-warning w-100 btn-sm fw-bold mb-2">
                    <i class="bi bi-plus-lg me-1"></i>New Tab
                </a>
                <a href="{{ route('game_configs.index') }}" class="btn btn-outline-secondary w-100 btn-sm">
                    <i class="bi bi-gear-wide-connected me-1"></i>Game Configs
                </a>
            </div>
        </div>

        {{-- Right Column: Main Table matching screenshot --}}
        <div class="col-lg-10">
            {{-- Filter & Count strip --}}
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 px-1">
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="showCreditSwitch" checked>
                        <label class="form-check-label text-secondary small" for="showCreditSwitch">Show credit</label>
                    </div>
                    <span class="text-secondary small">
                        Machines: <strong class="text-white">{{ $machinesCount }}</strong>
                    </span>
                    <span class="text-secondary small">
                        Active: <strong class="text-success">{{ $activeCount }}</strong>
                        <span class="ms-1" style="color:#10b981;">●●●</span>
                    </span>
                </div>

                {{-- Tab Search --}}
                <form method="GET" action="{{ route('tabs.index') }}" class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-black border-secondary text-secondary">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control bg-black text-white border-secondary"
                            placeholder="Search tab/IP/MAC..." value="{{ request('search') }}">
                        @if(request('search'))
                            <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary border-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="table-responsive rounded-2 border border-secondary" style="border-color:#1e3247 !important;">
                <table class="table table-dark tab-list-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" title="Unlock / Lock status">U</th>
                            <th class="text-center">NO</th>
                            <th class="text-center" title="Enabled">E</th>
                            <th class="text-center" title="Free / Occupied">F</th>
                            <th class="text-center" title="Active / Online">A</th>
                            <th>NAME</th>
                            <th>DEN...</th>
                            <th class="text-end">CREDITS</th>
                            <th class="text-end">PROMO</th>
                            <th class="text-end">LAST BET</th>
                            <th class="text-center">GAMES</th>
                            <th class="text-end">IN</th>
                            <th class="text-end">OUT</th>
                            <th class="text-end">BALANCE</th>
                            <th class="text-end">BILL IN</th>
                            <th class="text-end">FT IN</th>
                            <th class="text-end">FT OUT</th>
                            <th class="text-end">BET</th>
                            <th>CONFIG</th>
                            <th class="text-center">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tabs as $tab)
                            @php
                                $status = $tab->statusRecord;
                                $isUnlocked = ($tab->lock_status === 'unlocked');
                                $isEnabled  = ($tab->status == 1);
                                $isFree     = (!$status || $status->status === 'idle' || $status->current_balance == 0);
                                $isOnline   = ($tab->connection_status === 'online');

                                $creditsVal = $status ? (float)$status->current_balance : 0.00;
                                $lastBetVal = $status ? (float)$status->shift_last_bet : 0.00;
                                $gamesVal   = $status ? (int)$status->shift_games_count : 0;
                                $inVal      = $status ? (float)$status->shift_total_in : 0.00;
                                $outVal     = $status ? (float)$status->shift_total_out : 0.00;
                                $balanceVal = $inVal - $outVal;
                                $billInVal  = $inVal * 0.70;
                                $ftInVal    = $inVal * 0.30;
                                $ftOutVal   = $outVal;
                                $betVal     = $status ? (float)$status->shift_total_bet : 0.00;
                            @endphp
                            <tr>
                                {{-- U --}}
                                <td class="text-center">
                                    <span class="{{ $isUnlocked ? 'badge-u' : 'badge-l' }}">
                                        {{ $isUnlocked ? 'U' : 'L' }}
                                    </span>
                                </td>

                                {{-- NO --}}
                                <td class="text-center fw-bold text-white">{{ $tab->tab_number }}</td>

                                {{-- E --}}
                                <td class="text-center">
                                    <span class="{{ $isEnabled ? 'badge-y' : 'badge-n' }}">
                                        {{ $isEnabled ? 'Y' : 'N' }}
                                    </span>
                                </td>

                                {{-- F --}}
                                <td class="text-center">
                                    <span class="{{ $isFree ? 'badge-y' : 'badge-n' }}">
                                        {{ $isFree ? 'Y' : 'N' }}
                                    </span>
                                </td>

                                {{-- A --}}
                                <td class="text-center">
                                    <span class="{{ $isOnline ? 'badge-y' : 'badge-n' }}">
                                        {{ $isOnline ? 'Y' : 'N' }}
                                    </span>
                                </td>

                                {{-- NAME --}}
                                <td class="fw-bold text-white" style="font-family:'Segoe UI', sans-serif;">
                                    <span title="IP: {{ $tab->ip_address ?? 'No IP' }} | MAC: {{ $tab->active_mac ?? 'No MAC' }}">
                                        {{ $tab->table_name }}
                                    </span>
                                </td>

                                {{-- DENOMINATION --}}
                                <td>{{ number_format($tab->denomination, 2) }}</td>

                                {{-- CREDITS --}}
                                <td class="text-end fw-bold {{ $creditsVal > 0 ? 'text-warning' : 'text-white-50' }}">
                                    ${{ number_format($creditsVal, 2) }}
                                </td>

                                {{-- PROMO --}}
                                <td class="text-end text-white-50">$0.00</td>

                                {{-- LAST BET --}}
                                <td class="text-end text-secondary">${{ number_format($lastBetVal, 2) }}</td>

                                {{-- GAMES --}}
                                <td class="text-center text-white-50">{{ $gamesVal }}</td>

                                {{-- IN --}}
                                <td class="text-end text-white">${{ number_format($inVal, 2) }}</td>

                                {{-- OUT --}}
                                <td class="text-end text-white">${{ number_format($outVal, 2) }}</td>

                                {{-- BALANCE --}}
                                <td class="text-end {{ $balanceVal >= 0 ? 'val-positive' : 'val-negative' }}">
                                    ${{ number_format($balanceVal, 2) }}
                                </td>

                                {{-- BILL IN --}}
                                <td class="text-end text-white-50">${{ number_format($billInVal, 2) }}</td>

                                {{-- FT IN --}}
                                <td class="text-end text-white-50">${{ number_format($ftInVal, 2) }}</td>

                                {{-- FT OUT --}}
                                <td class="text-end text-white-50">${{ number_format($ftOutVal, 2) }}</td>

                                {{-- BET --}}
                                <td class="text-end text-white">${{ number_format($betVal, 2) }}</td>

                                {{-- CONFIG --}}
                                <td>
                                    <span class="badge" style="background:#1e293b; color:#38bdf8; font-size:10px; font-weight:normal;" title="{{ $tab->gameConfig?->name }}">
                                        {{ $tab->gameConfig?->name ?? 'None' }}
                                    </span>
                                    @if($tab->gameConfigs->count() > 1)
                                        <span class="badge bg-secondary" style="font-size:9px;" title="{{ $tab->gameConfigs->pluck('name')->join(', ') }}">
                                            +{{ $tab->gameConfigs->count() - 1 }}
                                        </span>
                                    @endif
                                </td>

                                {{-- ACTIONS --}}
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        {{-- Cashin Button --}}
                                        <button type="button" class="btn btn-outline-success btn-sm px-2"
                                            title="Cashin / Add Balance"
                                            data-bs-toggle="modal" data-bs-target="#cashinModal{{ $tab->id }}">
                                            <i class="bi bi-plus-circle"></i>
                                        </button>

                                        {{-- Cashout Button --}}
                                        @if($creditsVal > 0)
                                            <button type="button" class="btn btn-outline-warning btn-sm px-2"
                                                title="Cashout Balance (${{ number_format($creditsVal, 2) }})"
                                                data-bs-toggle="modal" data-bs-target="#cashoutModal{{ $tab->id }}">
                                                <i class="bi bi-wallet2"></i>
                                            </button>
                                        @endif

                                        {{-- Toggle Lock --}}
                                        <form method="POST" action="{{ route('tabs.toggle_lock', $tab->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary btn-sm px-2"
                                                title="{{ $isUnlocked ? 'Lock Tab' : 'Unlock Tab' }}">
                                                <i class="bi {{ $isUnlocked ? 'bi-lock' : 'bi-unlock' }}"></i>
                                            </button>
                                        </form>

                                        {{-- Edit --}}
                                        <a href="{{ route('tabs.edit', $tab->id) }}" class="btn btn-outline-primary btn-sm px-2" title="Edit Tab">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            {{-- Modal: Cashin --}}
                            <div class="modal fade" id="cashinModal{{ $tab->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content" style="background:#132235; border:1px solid #233c58; color:#fff;">
                                        <div class="modal-header border-secondary">
                                            <h6 class="modal-title text-warning fw-bold">
                                                <i class="bi bi-cash-stack me-2"></i>Cashin: {{ $tab->table_name }} (Tab #{{ $tab->tab_number }})
                                            </h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('tabs.cashin', $tab->id) }}">
                                            @csrf
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small text-secondary">Cashin Amount ($)</label>
                                                    <input type="number" step="1" min="1" name="amount" class="form-control bg-black text-warning fs-5 fw-bold border-secondary" placeholder="100.00" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small text-secondary">Player Name (Optional)</label>
                                                    <input type="text" name="player_name" class="form-control bg-black text-white border-secondary" value="{{ $status?->player_name }}" placeholder="e.g. John Doe">
                                                </div>
                                                <div class="row g-2 mb-2">
                                                    <div class="col-6">
                                                        <label class="form-label small text-secondary">Player Phone</label>
                                                        <input type="text" name="player_phone" class="form-control bg-black text-white border-secondary" value="{{ $status?->player_phone }}" placeholder="+1 555-0100">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small text-secondary">Player Card ID</label>
                                                        <input type="text" name="player_card_id" class="form-control bg-black text-white border-secondary" value="{{ $status?->player_card_id }}" placeholder="CARD-1234">
                                                    </div>
                                                </div>
                                                <div class="small text-secondary fst-italic">
                                                    <i class="bi bi-shield-check me-1"></i>A unique OTP will be generated for this session and maintained until cashout.
                                                </div>
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success btn-sm fw-bold">
                                                    <i class="bi bi-check-lg me-1"></i>Process Cashin
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Modal: Cashout --}}
                            @if($creditsVal > 0)
                            <div class="modal fade" id="cashoutModal{{ $tab->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content" style="background:#132235; border:1px solid #233c58; color:#fff;">
                                        <div class="modal-header border-secondary">
                                            <h6 class="modal-title text-danger fw-bold">
                                                <i class="bi bi-wallet2 me-2"></i>Cashout: {{ $tab->table_name }}
                                            </h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('tabs.cashout', $tab->id) }}">
                                            @csrf
                                            <div class="modal-body text-center py-4">
                                                <div class="text-secondary small mb-1">Available Tab Balance to Redeem</div>
                                                <div class="fs-2 fw-bold text-warning font-monospace mb-3">
                                                    ${{ number_format($creditsVal, 2) }}
                                                </div>
                                                @if($status?->player_name)
                                                    <div class="badge bg-dark border border-secondary text-white mb-2">
                                                        Player: {{ $status->player_name }}
                                                    </div>
                                                @endif
                                                @if($status?->current_otp)
                                                    <div class="text-secondary small">
                                                        Active OTP: <strong class="text-info font-monospace">{{ $status->current_otp }}</strong>
                                                    </div>
                                                @endif
                                                <div class="alert alert-warning border-0 small mt-3 text-start" style="background:#2a200a; color:#fbbf24;">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                    This will record a direct Cage CASHOUT transaction, redeem the full balance, and close the session.
                                                </div>
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger btn-sm fw-bold">
                                                    <i class="bi bi-cash me-1"></i>Confirm Cashout (${{ number_format($creditsVal, 2) }})
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif

                        @empty
                            <tr>
                                <td colspan="20" class="text-center py-4 text-secondary">
                                    No tabs configured yet. <a href="{{ route('tabs.create') }}" class="text-warning">Add your first Tab</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
