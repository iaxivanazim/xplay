<x-app-layout>
    {{-- Cage Dashboard Custom CSS --}}
    <style>
        .cage-dashboard-root {
            background: #09131d;
            min-height: calc(100vh - 70px);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: -24px;
            padding: 16px 20px;
        }

        /* Top KPI Bar */
        .cage-top-bar {
            background: #0d1a29;
            border: 1px solid #1a2f47;
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 16px;
        }
        .cage-kpi-bold {
            color: #ffffff;
            font-family: monospace;
            font-weight: 700;
            font-size: 13px;
        }
        .cage-credits-text {
            color: #f59e0b !important;
            font-family: monospace;
            font-weight: 800;
            font-size: 13px;
        }

        /* 7-Column Grid Layout */
        .tab-grid-container {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
        }
        @media (max-width: 1400px) {
            .tab-grid-container { grid-template-columns: repeat(6, 1fr); }
        }
        @media (max-width: 1100px) {
            .tab-grid-container { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 768px) {
            .tab-grid-container { grid-template-columns: repeat(2, 1fr); }
        }

        /* Tab Card Styling */
        .tab-card {
            background: #111f30;
            border-radius: 8px;
            padding: 16px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.18s ease-in-out;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 108px;
            user-select: none;
        }
        .tab-card:hover {
            transform: translateY(-2px);
            filter: brightness(1.12);
        }

        /* Status Borders */
        .tab-card.border-idle {
            border: 2px solid #10b981; /* Emerald Green */
        }
        .tab-card.border-active {
            border: 2px solid #f59e0b; /* Golden Amber */
            box-shadow: 0 0 14px rgba(245, 158, 11, 0.45);
        }
        .tab-card.border-locked {
            border: 2px solid #ef4444; /* Red */
        }
        .tab-card.border-disabled {
            border: 2px solid #334155; /* Slate */
            opacity: 0.6;
        }

        /* Card Content */
        .tab-card-num {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 4px;
        }
        .tab-card-name {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .tab-card-balance {
            font-family: monospace;
            font-size: 16px;
            font-weight: 700;
            color: #38bdf8;
            line-height: 1;
        }
        .tab-card.border-active .tab-card-balance {
            color: #fbbf24;
        }

        /* Right Sidebar Panel */
        .cage-sidebar-panel {
            background: #0d1a29;
            border: 1px solid #1a2f47;
            border-radius: 6px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 600px;
        }
        .cage-action-btn {
            background: #172a3d;
            color: #e2e8f0;
            border: 1px solid #2a4365;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 14px;
            border-radius: 6px;
            width: 100%;
            text-align: center;
            display: block;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 10px;
        }
        .cage-action-btn:hover {
            background: #1e3a5f;
            color: #ffffff;
        }
        .cage-btn-exit-solid {
            background: #b91c1c;
            color: #ffffff;
            border: 1px solid #ef4444;
            font-size: 13px;
            font-weight: 700;
            padding: 10px 14px;
            border-radius: 6px;
            width: 100%;
            text-align: center;
            display: block;
            text-decoration: none;
            transition: all 0.2s;
        }
        .cage-btn-exit-solid:hover {
            background: #dc2626;
            color: #ffffff;
        }
    </style>

    <div class="cage-dashboard-root">

        {{-- Flash Alerts --}}
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

        {{-- TOP KPI STRIP (Matching Screenshot) --}}
        <div class="cage-top-bar">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                {{-- Column 1: User / Period / Time --}}
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2 bg-primary text-white fw-bold" style="font-size:11px;">STA</span>
                    <div>
                        <div>User: <strong class="text-white">{{ auth()->user()->name ?? 'Cashier' }}</strong></div>
                        <div>Period: <span class="text-white-50">{{ now()->format('d-m-Y') }}</span></div>
                        <div>Time: <span class="text-white-50">{{ now()->format('H:i') }}</span></div>
                    </div>
                </div>

                {{-- Column 2: Total in / out / difference --}}
                <div>
                    <div>Total in: <span class="cage-kpi-bold">{{ number_format($totalIn, 2) }}</span></div>
                    <div>Total out: <span class="cage-kpi-bold">{{ number_format($totalOut, 2) }}</span></div>
                    <div>Difference: <span class="cage-kpi-bold {{ $difference >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($difference, 2) }}</span></div>
                </div>

                {{-- Column 3: Total bet / win / difference --}}
                <div>
                    <div>Total bet: <span class="cage-kpi-bold">{{ number_format($totalBet, 2) }}</span></div>
                    <div>Total win: <span class="cage-kpi-bold">{{ number_format($totalWin, 2) }}</span></div>
                    <div>Difference: <span class="cage-kpi-bold {{ $betWinDiff >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($betWinDiff, 2) }}</span></div>
                </div>

                {{-- Column 4: FT in / out / difference --}}
                <div>
                    <div>FT in: <span class="cage-kpi-bold">{{ number_format($ftIn, 2) }}</span></div>
                    <div>FT out: <span class="cage-kpi-bold">{{ number_format($ftOut, 2) }}</span></div>
                    <div>Difference: <span class="cage-kpi-bold text-danger">{{ number_format($ftDiff, 2) }}</span></div>
                </div>

                {{-- Column 5: Bill in / Hand pay / Credits --}}
                <div>
                    <div>Bill in: <span class="cage-kpi-bold">{{ number_format($billIn, 2) }}</span></div>
                    <div>Hand pay: <span class="cage-kpi-bold">0.00</span></div>
                    <div>Credits: <span class="cage-credits-text">{{ number_format($credits, 2) }}</span></div>
                </div>

                {{-- Column 6: Machines & Active --}}
                <div>
                    <div>Machines: <strong class="text-white">{{ $machinesCount }}</strong></div>
                    <div>Active: <strong class="text-success">{{ $activeCount }}</strong> <span style="color:#10b981;">●●●</span></div>
                </div>

                {{-- Column 7: Jackpots --}}
                <div>
                    <div>Megapot: <strong class="text-warning font-monospace">$9,942.23</strong></div>
                    <div>Tournament: <strong class="text-warning font-monospace">$300.00</strong></div>
                </div>

                {{-- Far Right Gear --}}
                <div>
                    <a href="{{ route('tabs.index') }}" class="text-secondary fs-5" title="Settings / Tabs Configuration">
                        <i class="bi bi-gear-fill"></i>
                    </a>
                </div>
            </div>
        </div>

        {{-- MAIN BODY: Grid on left + Sidebar on right --}}
        <div class="row g-3">
            {{-- Main 7-Column Grid of Tabs --}}
            <div class="col-xl-10 col-lg-9">
                <div class="tab-grid-container" id="tabGrid">
                    @forelse($tabs as $tab)
                        @php
                            $status = $tab->statusRecord;
                            $balance = $status ? (float)$status->current_balance : 0.00;
                            $hasBalance = ($balance > 0);
                            $isLocked = ($tab->lock_status === 'locked' || $tab->lock_status === 'break');
                            $isDisabled = ($tab->status == 0);

                            // Determine border class
                            $borderClass = 'border-idle';
                            if ($isDisabled) {
                                $borderClass = 'border-disabled';
                            } elseif ($isLocked) {
                                $borderClass = 'border-locked';
                            } elseif ($hasBalance) {
                                $borderClass = 'border-active';
                            }
                        @endphp

                        <div class="tab-card {{ $borderClass }}" onclick="openTabDrawer({{ $tab->id }})" title="Click to manage Tab #{{ $tab->tab_number }}">
                            <div class="tab-card-num">{{ $tab->tab_number }}</div>
                            <div class="tab-card-name">{{ $tab->table_name }}</div>
                            <div class="tab-card-balance">${{ number_format($balance, 2) }}</div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-5 text-secondary">
                            No tabs configured. <a href="{{ route('tabs.create') }}" class="text-warning">Deploy tabs</a>.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Right Sidebar --}}
            <div class="col-xl-2 col-lg-3">
                <div class="cage-sidebar-panel">
                    <div>
                        <div class="mb-4">
                            <div class="text-secondary small">Opening Float:</div>
                            <div class="cage-kpi-bold fs-5">0.00</div>
                        </div>
                        <div class="mb-4">
                            <div class="text-secondary small">Float Balance:</div>
                            <div class="cage-kpi-bold fs-5">0.00</div>
                        </div>
                    </div>

                    <div>
                        <button type="button" class="cage-action-btn" onclick="alert('Day & Shift active');">
                            Day & Shift
                        </button>

                        <a href="{{ route('tabs.index') }}" class="cage-action-btn" title="Toggle List View">
                            <i class="bi bi-list-ul me-1"></i>View (List)
                        </a>

                        <a href="{{ route('game_configs.index') }}" class="cage-action-btn" title="Game Configurations">
                            <i class="bi bi-gear-wide-connected me-1"></i>Game Config
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="mb-2">
                            @csrf
                            <button type="submit" class="cage-action-btn">Logout</button>
                        </form>

                        <a href="{{ route('tabs.index') }}" class="cage-btn-exit-solid">Exit</a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════════════════
         CASHIER TAB MANAGEMENT MODAL / DRAWER
         ══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="tabDrawerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background:#101e2e; border:1px solid #1f364d; color:#e2e8f0; border-radius:10px;">
                
                {{-- Header with Tab Info Banner --}}
                <div class="modal-header border-secondary py-3 px-4" style="background:#0c1724;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge rounded-circle p-3 bg-primary fs-5 fw-bold" id="drawer_tab_number">1</div>
                        <div>
                            <h5 class="modal-title fw-bold text-white mb-0" id="drawer_tab_name">Amatic-1</h5>
                            <div class="small text-secondary" id="drawer_tab_network">IP: 192.168.1.101 | MAC: 00:E0:4C:68:00:01</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge" id="drawer_lock_badge" style="font-size:11px;">UNLOCKED</span>
                        <span class="badge bg-success" id="drawer_online_badge" style="font-size:11px;">ONLINE</span>
                        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="modal-body p-4">
                    {{-- Balance & Session Summary Banner --}}
                    <div class="p-3 rounded-2 mb-4 d-flex justify-content-between align-items-center" style="background:#09131d; border:1px solid #1a2f47;">
                        <div>
                            <div class="text-secondary small">CURRENT BALANCE (PURE CASH / NO CHIPS)</div>
                            <div class="fs-2 fw-bold text-warning font-monospace" id="drawer_balance">$0.00</div>
                            <div class="small text-secondary" id="drawer_player_info">No active player assigned</div>
                        </div>
                        <div class="text-end">
                            <div class="text-secondary small">ACTIVE SESSION OTP</div>
                            <div class="fs-3 fw-bold font-monospace text-info" id="drawer_otp">&mdash;&mdash;&mdash;&mdash;</div>
                            <div class="small text-white-50" id="drawer_active_config">Game: Roulette</div>
                        </div>
                    </div>

                    {{-- Action Tabs --}}
                    <ul class="nav nav-pills mb-3 border-bottom border-secondary pb-2" id="drawerTabNav" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active btn-sm" id="tab-cashin-btn" data-bs-toggle="pill" data-bs-target="#pane-cashin" type="button">
                                <i class="bi bi-plus-circle me-1"></i>Cashin / Assign Player
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link btn-sm" id="tab-cashout-btn" data-bs-toggle="pill" data-bs-target="#pane-cashout" type="button">
                                <i class="bi bi-wallet2 me-1"></i>Cashout
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link btn-sm" id="tab-config-btn" data-bs-toggle="pill" data-bs-target="#pane-config" type="button">
                                <i class="bi bi-controller me-1"></i>Assign Game Config
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link btn-sm" id="tab-lock-btn" data-bs-toggle="pill" data-bs-target="#pane-lock" type="button">
                                <i class="bi bi-shield-lock me-1"></i>Lock / Break
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link btn-sm text-info" id="tab-recovery-btn" data-bs-toggle="pill" data-bs-target="#pane-recovery" type="button">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Disaster Recovery
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="drawerTabContent">
                        {{-- 1. CASHIN / ASSIGN PLAYER PANE --}}
                        <div class="tab-pane fade show active" id="pane-cashin" role="tabpanel">
                            <form id="cashinForm" method="POST" action="">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small">Cashin Amount ($) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-black border-secondary text-warning fw-bold">$</span>
                                            <input type="number" step="1" min="1" name="amount" class="form-control bg-black text-warning fs-5 fw-bold border-secondary" placeholder="100.00" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small">Player Name</label>
                                        <input type="text" name="player_name" id="cashin_player_name" class="form-control bg-black text-white border-secondary" placeholder="e.g. John Doe">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small">Player Phone</label>
                                        <input type="text" name="player_phone" id="cashin_player_phone" class="form-control bg-black text-white border-secondary" placeholder="+1 555-0100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small">Player Card ID / Member ID</label>
                                        <input type="text" name="player_card_id" id="cashin_player_card" class="form-control bg-black text-white border-secondary" placeholder="VIP-CARD-001">
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-dark border-secondary small text-secondary mb-2">
                                            <i class="bi bi-shield-check text-success me-1"></i>
                                            Transacted directly through Cage ledger as pure cash. An OTP will be generated and persisted for this session until Cashout.
                                        </div>
                                        <button type="submit" class="btn btn-success fw-bold px-4 py-2">
                                            <i class="bi bi-check-lg me-1"></i>Process Cashin &amp; Start Session
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        {{-- 2. CASHOUT PANE --}}
                        <div class="tab-pane fade" id="pane-cashout" role="tabpanel">
                            <form id="cashoutForm" method="POST" action="">
                                @csrf
                                <div class="text-center py-3">
                                    <div class="text-secondary small mb-1">Balance Available for Cashout</div>
                                    <div class="fs-1 fw-bold text-warning font-monospace mb-3" id="cashout_balance_display">$0.00</div>
                                    
                                    <div class="alert alert-warning border-0 small text-start mx-auto mb-4" style="background:#2a1f0a; color:#fbbf24; max-width:500px;">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        Clicking below will record a direct Cage CASHOUT ledger transaction, redeem the full currency balance, close the active player session, and return the tab to idle.
                                    </div>

                                    <button type="submit" class="btn btn-danger fw-bold px-5 py-2" id="cashoutSubmitBtn">
                                        <i class="bi bi-wallet2 me-1"></i>Confirm &amp; Redeem Cashout
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- 3. ASSIGN GAME CONFIG PANE (Cashier Side Game Switch) --}}
                        <div class="tab-pane fade" id="pane-config" role="tabpanel">
                            <form id="assignConfigForm" method="POST" action="">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-secondary small">Assign / Switch Game Configuration for this Tab</label>
                                    <select name="game_config_id" id="assign_game_config_select" class="form-select bg-black text-white border-secondary" required>
                                        @foreach($gameConfigs as $cfg)
                                            <option value="{{ $cfg->id }}">
                                                {{ $cfg->name }} ({{ $cfg->gameType?->name }}) - Limits: {{ $cfg->min_bet }} to {{ $cfg->max_bet }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text text-secondary" style="font-size:11px;">
                                        Cashier can assign any Game Config to this Tab. If not already assigned, it will automatically be added to this tab's assigned games list.
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-warning fw-bold px-4">
                                    <i class="bi bi-check2-circle me-1"></i>Assign Config to Tab
                                </button>
                            </form>
                        </div>

                        {{-- 4. LOCK / BREAK PANE --}}
                        <div class="tab-pane fade" id="pane-lock" role="tabpanel">
                            <form id="toggleLockForm" method="POST" action="">
                                @csrf
                                <div class="p-3 text-center">
                                    <div class="mb-3">Current Lock Status: <strong class="fs-5 text-white" id="current_lock_label">UNLOCKED</strong></div>
                                    <p class="text-secondary small mb-4">
                                        Locking the tab prevents unauthorized betting while player takes a break. The session, balance, and OTP are strictly preserved.
                                    </p>
                                    <button type="submit" class="btn btn-outline-warning fw-bold px-4 py-2" id="toggleLockBtn">
                                        <i class="bi bi-lock me-1"></i>Toggle Lock / Break
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- 5. DISASTER RECOVERY & FAILURE RESTORATION PANE --}}
                        <div class="tab-pane fade" id="pane-recovery" role="tabpanel">
                            <div class="p-3 rounded" style="background:#09131d; border:1px solid #1a2f47;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-info fw-bold mb-0">
                                        <i class="bi bi-hdd-network me-2"></i>Failure Management &amp; State Recovery Snapshot
                                    </h6>
                                    <span class="badge bg-dark border border-secondary text-secondary" id="recovery_synced_badge">Synced: Just now</span>
                                </div>
                                <p class="text-secondary small mb-3">
                                    In case of network dropout, crash, or tab hardware reboot, this snapshot restores the exact session, OTP, and balance to the Tab hardware.
                                </p>
                                <pre class="p-3 rounded text-warning small font-monospace" style="background:#050c14; border:1px solid #142232; max-height:160px; overflow-y:auto;" id="recovery_json_preview">{ ... }</pre>
                                
                                <button type="button" class="btn btn-outline-info btn-sm fw-bold mt-2" onclick="triggerRestore()">
                                    <i class="bi bi-arrow-repeat me-1"></i>Push Recovery Snapshot to Tab
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary py-2 px-4" style="background:#0c1724;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let currentTabId = null;
        const drawerModal = new bootstrap.Modal(document.getElementById('tabDrawerModal'));

        function openTabDrawer(tabId) {
            currentTabId = tabId;

            // Set Form action URLs
            document.getElementById('cashinForm').action = `/dashboard/tab/${tabId}/cashin`;
            document.getElementById('cashoutForm').action = `/dashboard/tab/${tabId}/cashout`;
            document.getElementById('toggleLockForm').action = `/dashboard/tab/${tabId}/toggle-lock`;
            document.getElementById('assignConfigForm').action = `/dashboard/tab/${tabId}/assign-config`;

            // Fetch live tab details
            fetch(`/dashboard/tab/${tabId}/details`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;

                    const t = data.tab;
                    const s = data.status;

                    document.getElementById('drawer_tab_number').innerText = t.tab_number;
                    document.getElementById('drawer_tab_name').innerText = t.table_name;
                    document.getElementById('drawer_tab_network').innerText = `IP: ${t.ip_address || 'Unassigned'} | MAC: ${t.active_mac || 'Unbound'}`;

                    // Lock status badge
                    const lockBadge = document.getElementById('drawer_lock_badge');
                    if (t.lock_status === 'unlocked') {
                        lockBadge.className = 'badge bg-success';
                        lockBadge.innerText = 'UNLOCKED';
                        document.getElementById('current_lock_label').innerText = 'UNLOCKED';
                        document.getElementById('toggleLockBtn').innerHTML = '<i class="bi bi-lock me-1"></i>Lock Tab (Break Mode)';
                    } else {
                        lockBadge.className = 'badge bg-danger';
                        lockBadge.innerText = t.lock_status.toUpperCase();
                        document.getElementById('current_lock_label').innerText = t.lock_status.toUpperCase();
                        document.getElementById('toggleLockBtn').innerHTML = '<i class="bi bi-unlock me-1"></i>Unlock Tab';
                    }

                    // Balance & Player
                    const bal = parseFloat(s.current_balance || 0).toFixed(2);
                    document.getElementById('drawer_balance').innerText = '$' + bal;
                    document.getElementById('cashout_balance_display').innerText = '$' + bal;

                    if (s.player_name) {
                        document.getElementById('drawer_player_info').innerText = `Player: ${s.player_name} (${s.player_phone || 'No Phone'})`;
                        document.getElementById('cashin_player_name').value = s.player_name;
                        document.getElementById('cashin_player_phone').value = s.player_phone || '';
                        document.getElementById('cashin_player_card').value = s.player_card_id || '';
                    } else {
                        document.getElementById('drawer_player_info').innerText = 'No active player assigned';
                        document.getElementById('cashin_player_name').value = '';
                        document.getElementById('cashin_player_phone').value = '';
                        document.getElementById('cashin_player_card').value = '';
                    }

                    // OTP
                    document.getElementById('drawer_otp').innerText = s.current_otp || '------';
                    document.getElementById('drawer_active_config').innerText = `Config: ${t.game_config_name || 'None'}`;

                    // Config selector
                    if (t.game_config_id) {
                        document.getElementById('assign_game_config_select').value = t.game_config_id;
                    }

                    // Enable/disable cashout button based on balance
                    const cashoutBtn = document.getElementById('cashoutSubmitBtn');
                    if (parseFloat(bal) > 0) {
                        cashoutBtn.disabled = false;
                    } else {
                        cashoutBtn.disabled = true;
                    }

                    // Recovery data
                    document.getElementById('recovery_synced_badge').innerText = `Synced: ${s.last_synced_at || 'Just now'}`;
                    document.getElementById('recovery_json_preview').innerText = JSON.stringify(s.recovery_data || {
                        tab_id: t.id,
                        tab_name: t.table_name,
                        balance: bal,
                        otp: s.current_otp,
                        player: s.player_name,
                    }, null, 2);

                    // Show drawer
                    drawerModal.show();
                })
                .catch(err => {
                    console.error('Error fetching tab details:', err);
                });
        }

        function triggerRestore() {
            if (!currentTabId) return;
            fetch(`/dashboard/tab/${currentTabId}/restore`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                }
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || 'State restored successfully.');
            })
            .catch(err => {
                alert('Restore request completed.');
            });
        }
    </script>
    @endpush
</x-app-layout>