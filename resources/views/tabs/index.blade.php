<x-app-layout>
<div class="content-wrapper p-3">

{{-- ═══ HEADER BAR ═══════════════════════════════════════════════════ --}}
<div class="tabs-header-bar mb-3 px-3 py-2 rounded" style="background:#0d1117; border:1px solid #1e2a3a;">
    <div class="row gx-4 gy-1 align-items-center">
        {{-- Aggregate stats --}}
        <div class="col-auto">
            <span class="text-secondary" style="font-size:11px;">Machines:</span>
            <span class="text-white fw-bold ms-1">{{ $totalCount }}</span>
        </div>
        <div class="col-auto">
            <span class="text-secondary" style="font-size:11px;">Active Sessions:</span>
            <span class="fw-bold ms-1" style="color:#6fcf97;">{{ $activeCount }}</span>
        </div>
        <div class="col-auto">
            <span class="text-secondary" style="font-size:11px;">Total Credits:</span>
            <span class="text-warning fw-bold ms-1" id="hdr-total-credits">—</span>
        </div>
        <div class="col-auto">
            <span class="text-secondary" style="font-size:11px;">Shift In:</span>
            <span class="text-white fw-bold ms-1" id="hdr-shift-in">—</span>
        </div>
        <div class="col-auto">
            <span class="text-secondary" style="font-size:11px;">Shift Out:</span>
            <span class="text-white fw-bold ms-1" id="hdr-shift-out">—</span>
        </div>
        <div class="col-auto">
            <span class="text-secondary" style="font-size:11px;">Shift Bet:</span>
            <span class="text-white fw-bold ms-1" id="hdr-shift-bet">—</span>
        </div>

        {{-- Right-side controls --}}
        <div class="col ms-auto d-flex justify-content-end gap-2 align-items-center">
            {{-- Game type filter --}}
            <form method="GET" action="{{ route('tabs.index') }}" class="d-flex align-items-center gap-2">
                <select name="game_type" class="form-select form-select-sm bg-black text-white border-secondary" style="width:130px;" onchange="this.form.submit()">
                    <option value="">All Games</option>
                    @foreach($gameTypes as $gt)
                        <option value="{{ $gt->code }}" {{ $gameTypeFilter === $gt->code ? 'selected' : '' }}>{{ $gt->name }}</option>
                    @endforeach
                </select>
                {{-- Status filter --}}
                <select name="status" class="form-select form-select-sm bg-black text-white border-secondary" style="width:110px;" onchange="this.form.submit()">
                    <option value="all"    {{ $statusFilter === 'all'      ? 'selected' : '' }}>All</option>
                    <option value="active" {{ $statusFilter === 'active'   ? 'selected' : '' }}>Enabled</option>
                    <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Disabled</option>
                </select>
            </form>

            <a href="{{ route('tabs.create') }}" class="btn btn-warning btn-sm fw-bold">
                <i class="bi bi-plus-lg me-1"></i>Add Tab
            </a>
        </div>
    </div>
</div>

{{-- ═══ FLASH MESSAGES ══════════════════════════════════════════════ --}}
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 mb-3 py-2"
        style="background:#0f2e1a; color:#6fcf97; font-size:13px;">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 mb-3 py-2"
        style="background:#2e0d0d; color:#eb5757; font-size:13px;">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- ═══ TABS TABLE ═══════════════════════════════════════════════════ --}}
<div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0 tabs-list-table">
        <thead>
            <tr style="font-size:11px; letter-spacing:0.06em; color:#666; border-bottom:1px solid #222;">
                <th class="text-center" style="width:40px;">LOCK</th>
                <th class="text-center" style="width:48px;">NO</th>
                <th class="text-center" style="width:36px;" title="Enabled">E</th>
                <th class="text-center" style="width:36px;" title="Has Active Session">A</th>
                <th class="text-center" style="width:36px;" title="Online">●</th>
                <th>NAME</th>
                <th>GAME</th>
                <th class="text-end">DENOM</th>
                <th class="text-end">CREDITS</th>
                <th class="text-end">LAST BET</th>
                <th class="text-center">GAMES</th>
                <th class="text-end">SHIFT IN</th>
                <th class="text-end">SHIFT OUT</th>
                <th class="text-end" style="min-width:90px;">BALANCE</th>
                <th class="text-end">SHIFT BET</th>
                <th class="text-center" style="width:120px;">STATUS</th>
                <th class="text-center" style="width:80px;">ACTIONS</th>
            </tr>
        </thead>
        <tbody>
        @forelse($tabs as $tab)
            @php
                $ts        = $tab->tabStatus;
                $hasSession= $ts?->hasActiveSession() ?? false;
                $isEnabled = $tab->isEnabled();
                $isLocked  = $tab->isLocked();
                $lockStatus= $tab->lock_status ?? 'unlocked';
                $connStatus= $ts?->connection_status ?? 'offline';
                $balance   = (float) ($ts?->current_balance ?? 0);
                $shiftIn   = (float) ($ts?->shift_total_in ?? 0);
                $shiftOut  = (float) ($ts?->shift_total_out ?? 0);
                $shiftBet  = (float) ($ts?->shift_total_bet ?? 0);
                $lastBet   = (float) ($ts?->shift_last_bet ?? 0);
                $games     = $ts?->shift_games_count ?? 0;
                $netBalance= $shiftIn - $shiftOut;

                // Row colour based on session state
                $rowAccent = match(true) {
                    !$isEnabled              => '#1a1a1a',
                    $isLocked                => '#2e1a0a',
                    $hasSession && $balance > 0 => '#0d1f1a',
                    $hasSession              => '#0d1a2e',
                    default                  => 'transparent',
                };

                // Connection dot colour
                $connDot = match($connStatus) {
                    'online'       => '#6fcf97',
                    'disconnected' => '#eb5757',
                    default        => '#555',
                };

                // Game type badge colour
                $gameColor = match($tab->gameType?->code ?? '') {
                    'BAC' => '#c9a227',
                    'AB'  => '#4caf50',
                    'ROL' => '#9c27b0',
                    default => '#555',
                };
            @endphp
            <tr style="background:{{ $rowAccent }}; border-bottom:1px solid #1a1a1a; font-size:13px;"
                id="tab-row-{{ $tab->id }}">

                {{-- Lock status button --}}
                <td class="text-center">
                    @if($lockStatus === 'locked')
                        <form method="POST" action="{{ route('tabs.unlock', $tab->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm py-0 px-2" style="font-size:11px;" title="Locked — click to unlock">
                                <i class="bi bi-lock-fill"></i>
                            </button>
                        </form>
                    @elseif($lockStatus === 'break')
                        <form method="POST" action="{{ route('tabs.unlock', $tab->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm py-0 px-2" style="font-size:11px;" title="On break — click to unlock">
                                <i class="bi bi-pause-fill"></i>
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('tabs.lock', $tab->id) }}">
                            @csrf
                            <input type="hidden" name="lock_status" value="locked">
                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:11px;" title="Unlocked — click to lock">
                                <i class="bi bi-unlock"></i>
                            </button>
                        </form>
                    @endif
                </td>

                {{-- Tab number --}}
                <td class="text-center fw-bold text-white">{{ $tab->id }}</td>

                {{-- Enabled --}}
                <td class="text-center">
                    <span style="color: {{ $isEnabled ? '#6fcf97' : '#555' }}; font-size:12px;">
                        {{ $isEnabled ? 'Y' : 'N' }}
                    </span>
                </td>

                {{-- Active session --}}
                <td class="text-center">
                    @if($hasSession)
                        <span style="color:#6fcf97; font-size:14px;" title="OTP: {{ $ts?->current_otp }}">●</span>
                    @else
                        <span style="color:#333; font-size:14px;">●</span>
                    @endif
                </td>

                {{-- Connection dot --}}
                <td class="text-center">
                    <span style="color:{{ $connDot }}; font-size:14px;" title="{{ $connStatus }}">●</span>
                </td>

                {{-- Name --}}
                <td>
                    <div class="fw-bold text-white">{{ $tab->table_name }}</div>
                    @if($tab->active_mac)
                        <div style="font-size:10px; color:#555;">{{ $tab->active_mac }}</div>
                    @else
                        <div style="font-size:10px; color:#444; font-style:italic;">no MAC</div>
                    @endif
                </td>

                {{-- Game type --}}
                <td>
                    <span class="badge rounded-pill px-2 py-1"
                        style="background:{{ $gameColor }}22; color:{{ $gameColor }}; border:1px solid {{ $gameColor }}; font-size:11px;">
                        {{ $tab->gameType?->code ?? '—' }}
                    </span>
                    @if($tab->config?->preset)
                        <div style="font-size:10px; color:#888; margin-top:2px;" title="Preset: {{ $tab->config->preset->name }}">
                            {{ \Illuminate\Support\Str::limit($tab->config->preset->name, 14) }}
                        </div>
                    @endif
                </td>

                {{-- Denomination --}}
                <td class="text-end text-secondary" style="font-size:12px;">
                    {{ number_format((float)$tab->denomination, 2) }}
                </td>

                {{-- Credits --}}
                <td class="text-end fw-bold" style="color:{{ $balance > 0 ? '#ffc107' : '#888' }};">
                    {{ number_format($balance, 2) }}
                </td>

                {{-- Last bet --}}
                <td class="text-end text-secondary" style="font-size:12px;">
                    {{ $lastBet > 0 ? number_format($lastBet, 2) : '—' }}
                </td>

                {{-- Games count --}}
                <td class="text-center text-secondary" style="font-size:12px;">{{ $games }}</td>

                {{-- Shift In --}}
                <td class="text-end text-white" style="font-size:12px;">
                    {{ $shiftIn > 0 ? number_format($shiftIn, 2) : '—' }}
                </td>

                {{-- Shift Out --}}
                <td class="text-end text-white" style="font-size:12px;">
                    {{ $shiftOut > 0 ? number_format($shiftOut, 2) : '—' }}
                </td>

                {{-- Balance (net) --}}
                <td class="text-end fw-bold" style="font-size:13px;
                    color:{{ $netBalance > 0 ? '#6fcf97' : ($netBalance < 0 ? '#eb5757' : '#888') }};">
                    {{ $netBalance != 0 ? number_format($netBalance, 2) : '—' }}
                </td>

                {{-- Shift Bet --}}
                <td class="text-end text-secondary" style="font-size:12px;">
                    {{ $shiftBet > 0 ? number_format($shiftBet, 2) : '—' }}
                </td>

                {{-- Status pill --}}
                <td class="text-center">
                    @php
                        [$pillBg, $pillColor, $pillText] = match(true) {
                            !$isEnabled   => ['#1a1a1a', '#555',    'DISABLED'],
                            $isLocked     => ['#3b1a00', '#ff9800', 'LOCKED'],
                            $lockStatus === 'break' => ['#1a1a3b', '#7986cb', 'BREAK'],
                            $hasSession   => ['#0f2e1a', '#6fcf97', 'IN SESSION'],
                            default       => ['#1a1a1a', '#aaa',   'IDLE'],
                        };
                    @endphp
                    <span class="badge rounded-pill px-2 py-1"
                        style="background:{{ $pillBg }}; color:{{ $pillColor }}; border:1px solid {{ $pillColor }}44; font-size:10px; letter-spacing:0.04em;">
                        {{ $pillText }}
                    </span>
                </td>

                {{-- Actions --}}
                <td class="text-center">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm py-0 px-2 dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" style="font-size:11px;">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="background:#1a1a1a; border:1px solid #333; min-width:160px;">
                            <li>
                                <a class="dropdown-item text-white" href="{{ route('tabs.edit', $tab->id) }}"
                                    style="font-size:12px;">
                                    <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Config
                                </a>
                            </li>
                            <li><hr class="dropdown-divider" style="border-color:#333;"></li>

                            {{-- Enable / Disable --}}
                            @if($isEnabled)
                                <li>
                                    <form method="POST" action="{{ route('tabs.disable', $tab->id) }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger" style="font-size:12px;"
                                            onclick="return confirm('Disable tab {{ $tab->table_name }}?')">
                                            <i class="bi bi-slash-circle me-2"></i>Disable Tab
                                        </button>
                                    </form>
                                </li>
                            @else
                                <li>
                                    <form method="POST" action="{{ route('tabs.enable', $tab->id) }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-success" style="font-size:12px;">
                                            <i class="bi bi-check-circle me-2"></i>Enable Tab
                                        </button>
                                    </form>
                                </li>
                            @endif

                            {{-- Lock / Unlock / Break --}}
                            @if($lockStatus === 'unlocked')
                                <li>
                                    <form method="POST" action="{{ route('tabs.lock', $tab->id) }}">
                                        @csrf
                                        <input type="hidden" name="lock_status" value="locked">
                                        <button type="submit" class="dropdown-item text-warning" style="font-size:12px;">
                                            <i class="bi bi-lock me-2"></i>Lock Tab
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('tabs.lock', $tab->id) }}">
                                        @csrf
                                        <input type="hidden" name="lock_status" value="break">
                                        <button type="submit" class="dropdown-item" style="font-size:12px; color:#7986cb;">
                                            <i class="bi bi-pause-circle me-2"></i>Put on Break
                                        </button>
                                    </form>
                                </li>
                            @else
                                <li>
                                    <form method="POST" action="{{ route('tabs.unlock', $tab->id) }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-success" style="font-size:12px;">
                                            <i class="bi bi-unlock me-2"></i>Unlock Tab
                                        </button>
                                    </form>
                                </li>
                            @endif

                            <li><hr class="dropdown-divider" style="border-color:#333;"></li>

                            {{-- MAC --}}
                            @if($tab->active_mac)
                                <li>
                                    <form method="POST" action="{{ route('tabs.unregister-mac', $tab->id) }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-secondary" style="font-size:12px;"
                                            onclick="return confirm('Unregister MAC from {{ $tab->table_name }}?')">
                                            <i class="bi bi-pc-display me-2"></i>Unregister MAC
                                        </button>
                                    </form>
                                </li>
                            @else
                                <li>
                                    <a class="dropdown-item text-secondary" href="{{ route('tabs.edit', $tab->id) }}"
                                        style="font-size:12px;">
                                        <i class="bi bi-pc-display me-2"></i>Register MAC
                                    </a>
                                </li>
                            @endif

                            <li><hr class="dropdown-divider" style="border-color:#333;"></li>

                            {{-- Delete --}}
                            <li>
                                <form method="POST" action="{{ route('tabs.destroy', $tab->id) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger" style="font-size:12px;"
                                        onclick="return confirm('Delete tab {{ $tab->table_name }}? This cannot be undone.')">
                                        <i class="bi bi-trash me-2"></i>Delete Tab
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="17" class="text-center py-5">
                    <div style="font-size:40px; opacity:0.2;">🎰</div>
                    <div class="text-muted mt-2" style="font-size:14px;">No tabs configured yet.</div>
                    <a href="{{ route('tabs.create') }}" class="btn btn-warning btn-sm mt-3">
                        <i class="bi bi-plus-lg me-1"></i>Add First Tab
                    </a>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

</div>
</x-app-layout>

<style>
.tabs-list-table thead th {
    background: #0d1117;
    border-color: #222;
    padding: 8px 10px;
}
.tabs-list-table tbody td {
    padding: 7px 10px;
    vertical-align: middle;
    border-color: #1a1a1a;
}
.tabs-list-table tbody tr:hover td { background: rgba(255,255,255,0.03) !important; }
.dropdown-item:hover { background: #2a2a2a !important; }
</style>

@push('scripts')
<script>
// Poll /api/v1/tabs/statuses every 10s and update header aggregates + row credits
function refreshTabStatuses() {
    fetch('/api/v1/tabs/statuses')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            const agg = data.aggregates;
            const fmt = v => '$' + parseFloat(v).toLocaleString('en', {minimumFractionDigits:2, maximumFractionDigits:2});

            document.getElementById('hdr-total-credits').textContent = fmt(agg.total_credits);
            document.getElementById('hdr-shift-in').textContent      = fmt(agg.shift_total_in);
            document.getElementById('hdr-shift-out').textContent     = fmt(agg.shift_total_out);
            document.getElementById('hdr-shift-bet').textContent     = fmt(agg.shift_total_bet);

            // Update individual row credits
            data.tabs.forEach(tab => {
                const row = document.getElementById('tab-row-' + tab.tab_id);
                if (!row) return;
                // update connection dot colour
                const dots = row.querySelectorAll('td:nth-child(5) span');
                if (dots[0]) {
                    dots[0].style.color = tab.connection_status === 'online' ? '#6fcf97'
                        : (tab.connection_status === 'disconnected' ? '#eb5757' : '#555');
                    dots[0].title = tab.connection_status;
                }
            });
        })
        .catch(() => {});
}

refreshTabStatuses();
setInterval(refreshTabStatuses, 10000);
</script>
@endpush
