<x-app-layout>
@php
    // Collect all tables that have been open for more than 25 hours and are not closed
    $overdueAlerts = $tables->filter(function ($t) {
        $float = $t->currentFloat;
        if (!$float || !$float->opened_at) return false;
        return \Carbon\Carbon::parse($float->opened_at)->diffInHours(now()) >= 25;
    })->map(function ($t) {
        $float   = $t->currentFloat;
        $hours   = \Carbon\Carbon::parse($float->opened_at)->diffInHours(now());
        $minutes = \Carbon\Carbon::parse($float->opened_at)->diffInMinutes(now()) % 60;
        return [
            'table_name' => $t->table_name,
            'gameday'    => $float->gameday,
            'opened_at'  => \Carbon\Carbon::parse($float->opened_at)->format('d M Y, h:i A'),
            'duration'   => "{$hours}h {$minutes}m",
        ];
    })->values();
@endphp
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <h5 class="text-warning mb-0">
                    <i class="bi bi-grid-3x3-gap-fill me-2"></i>Game Tables
                </h5>

                {{-- ── Overdue Notification Bell ── --}}
                @if($overdueAlerts->isNotEmpty())
                <div class="position-relative" id="notif-bell-wrapper">
                    <button id="notif-bell-btn"
                        class="btn p-0 border-0 position-relative notif-bell-btn"
                        onclick="toggleNotifPanel()"
                        title="{{ $overdueAlerts->count() }} table(s) open for over 25 hours">
                        <i class="bi bi-bell-fill notif-bell-icon"></i>
                        <span class="notif-badge" id="notif-badge">{{ $overdueAlerts->count() }}</span>
                    </button>

                    {{-- Dropdown Panel --}}
                    <div id="notif-panel" class="notif-panel d-none">
                        <div class="notif-panel-header">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                            <span>Long-Running Sessions</span>
                            <button class="notif-close-btn ms-auto" onclick="toggleNotifPanel()">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="notif-panel-body">
                            @foreach($overdueAlerts as $alert)
                            <div class="notif-item">
                                <div class="notif-item-header">
                                    <i class="bi bi-table text-warning me-1" style="font-size:11px;"></i>
                                    <span class="notif-table-name">{{ $alert['table_name'] }}</span>
                                    <span class="notif-duration-badge">{{ $alert['duration'] }}</span>
                                </div>
                                <div class="notif-item-meta">
                                    <span><i class="bi bi-calendar3 me-1"></i>{{ $alert['gameday'] }}</span>
                                    <span><i class="bi bi-clock me-1"></i>Opened {{ $alert['opened_at'] }}</span>
                                </div>
                                <div class="notif-item-warning">
                                    <i class="bi bi-exclamation-circle-fill me-1"></i>
                                    Session unclosed for more than 25 hours. Please close this table.
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="notif-panel-footer">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ $overdueAlerts->count() }} session(s) require attention
                        </div>
                    </div>
                </div>
                @endif
            </div>
            
            <div class="d-flex flex-wrap align-items-center gap-2">
                {{-- Search & Sort --}}
                <form method="GET" action="{{ route('game_tables.index') }}" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-black border-secondary text-secondary">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search"
                            class="form-control bg-black text-white border-secondary"
                            placeholder="Search table..." value="{{ request('search') }}">
                        @if(request('search'))
                            <a href="{{ route('game_tables.index', ['status' => $status]) }}" class="btn btn-outline-secondary border-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-1">
                        <select name="sort_by" class="form-select form-select-sm bg-black text-white border-secondary" 
                                onchange="this.form.submit()" style="width: 130px;">
                            <option value="created_at" {{ request('sort_by') == 'created_at' ? 'selected' : '' }}>Newest</option>
                            <option value="table_name" {{ request('sort_by') == 'table_name' ? 'selected' : '' }}>Name</option>
                            <option value="id" {{ request('sort_by') == 'id' ? 'selected' : '' }}>ID</option>
                        </select>

                        <select name="order" class="form-select form-select-sm bg-black text-white border-secondary" 
                                onchange="this.form.submit()" style="width: 85px;">
                            <option value="desc" {{ request('order') == 'desc' ? 'selected' : '' }}>DESC</option>
                            <option value="asc" {{ request('order') == 'asc' ? 'selected' : '' }}>ASC</option>
                        </select>
                    </div>
                </form>

                <div class="vr bg-secondary mx-1 d-none d-lg-block" style="height: 24px; opacity: 0.4;"></div>

                {{-- Status Filter --}}
                <div class="btn-group" role="group">
                    <a href="{{ route('game_tables.index', ['status' => 1, 'search' => request('search'), 'sort_by' => request('sort_by'), 'order' => request('order'), 'mac_filter' => $macFilter]) }}"
                        class="btn btn-sm {{ $status == 1 ? 'btn-warning' : 'btn-outline-warning' }}">
                        Active
                    </a>
                    <a href="{{ route('game_tables.index', ['status' => 0, 'search' => request('search'), 'sort_by' => request('sort_by'), 'order' => request('order'), 'mac_filter' => $macFilter]) }}"
                        class="btn btn-sm {{ $status == 0 ? 'btn-warning' : 'btn-outline-warning' }}">
                        Inactive
                    </a>
                </div>

                <div class="vr bg-secondary mx-1 d-none d-lg-block" style="height: 24px; opacity: 0.4;"></div>

                {{-- MAC Filter --}}
                <div class="btn-group" role="group">
                    <a href="{{ route('game_tables.index', ['mac_filter' => 'all', 'status' => $status, 'search' => request('search'), 'sort_by' => request('sort_by'), 'order' => request('order')]) }}"
                        class="btn btn-sm {{ $macFilter == 'all' ? 'btn-warning' : 'btn-outline-warning' }}">
                        All
                    </a>
                    <a href="{{ route('game_tables.index', ['mac_filter' => 'bound', 'status' => $status, 'search' => request('search'), 'sort_by' => request('sort_by'), 'order' => request('order')]) }}"
                        class="btn btn-sm {{ $macFilter == 'bound' ? 'btn-warning' : 'btn-outline-warning' }}">
                        Bound
                    </a>
                    <a href="{{ route('game_tables.index', ['mac_filter' => 'unbound', 'status' => $status, 'search' => request('search'), 'sort_by' => request('sort_by'), 'order' => request('order')]) }}"
                        class="btn btn-sm {{ $macFilter == 'unbound' ? 'btn-warning' : 'btn-outline-warning' }}">
                        Unbound
                    </a>
                </div>

                <a href="{{ route('game_tables.create') }}" class="btn btn-warning btn-sm fw-bold">
                    <i class="bi bi-plus-lg me-1"></i>New Table
                </a>
            </div>
        </div>

        {{-- Session Flash --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 mb-4"
                style="background:#0f2e1a; color:#6fcf97;">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Table Grid --}}
        @forelse($tables as $table)
            @php
                $config = $table->config;
                $preset = $config?->preset;
                $chip = $preset?->chipPreset;
                $colors = ['red', 'blue', 'green', 'purple', 'gold'];
                $feltColor = $table->felt_color ?? '#006400';

                $gameBadgeColor = match ($table->gameType?->code ?? '') {
                    'BAC' => '#c9a227',
                    'BJ' => '#0047ab',
                    'DT' => '#b30000',
                    'AB' => '#006400',
                    '3CP' => '#5a007a',
                    'MF' => '#1a6b6b',
                    'CW' => '#8b0000',
                    default => '#444',
                };
            @endphp

            <div class="card mb-4 border-0 overflow-hidden"
                style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.07); border-left: 4px solid {{ $gameBadgeColor }} !important;">
                <div class="card-body p-0">
                    <div class="row g-0">

                        {{-- ── LEFT: Felt Visual ── --}}
                        <div class="col-md-1 d-flex align-items-center justify-content-center"
                            style="background:{{ $feltColor }}22; border-right:1px solid #222; min-height:130px;">
                            <div class="text-center px-2">
                                <div
                                    style="width:40px; height:40px; border-radius:50%; background:{{ $feltColor }};
                                        border:3px dashed white; margin:auto;
                                        box-shadow: 0 0 12px {{ $feltColor }}88;">

                                    <span class="text-white fw-bold" style="font-size:10px;">{{ $table->id }}</span>

                                </div>
                                <div class="mt-2" style="font-size:10px; letter-spacing:0.05em; color:#fff;">FELT
                                </div>
                            </div>
                        </div>

                        {{-- ── CENTER: Table Info ── --}}
                        <div class="col-md-6 p-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-bold text-white fs-6">{{ $table->table_name }}</span>
                                <span class="badge rounded-pill px-2 py-1"
                                    style="background:{{ $gameBadgeColor }}33; color:{{ $gameBadgeColor }};
                                         border:1px solid {{ $gameBadgeColor }}; font-size:11px;">
                                    {{ $table->gameType?->name ?? 'N/A' }}
                                </span>
                                @if ($table->status == 1)
                                    <span class="badge rounded-pill"
                                        style="background:#0f2e1a; color:#6fcf97; border:1px solid #6fcf97; font-size:10px;">
                                        ● ACTIVE
                                    </span>
                                @else
                                    <span class="badge rounded-pill"
                                        style="background:#2e1010; color:#eb5757; border:1px solid #eb5757; font-size:10px;">
                                        ● INACTIVE
                                    </span>
                                @endif
                                @php $isOpen = $table->isFloatOpen(); @endphp
                                @if($isOpen)
                                    <span class=""
                                        style=" color:#ffffff;  font-size:15px;">
                                        Game Day: <b>{{ $table->currentFloat->gameday }}</b>
                                    </span>
                                @else
                                    <span class=""
                                        style=" color:#ffffff;  font-size:10px;">
                                        No Open Float
                                    </span>
                                @endif
                            </div>

                            <div class="d-flex flex-wrap gap-3 mt-2">
                                @if ($table->active_mac)
                                    <span class="text-secondary small">
                                        <i class="bi bi-pc-display me-1"></i>{{ $table->active_mac }}
                                    </span>
                                @endif
                                @php $liveFloat = $table->live_float; @endphp
                                @if (!is_null($liveFloat))
                                    <span class="text-secondary small">
                                        <i class="bi bi-cash-stack me-1"></i>Float:
                                        <span class="text-warning">{{ number_format($liveFloat, 2) }}</span>
                                    </span>
                                @elseif($table->float)
                                    {{-- fallback to reference float if no open session --}}
                                    <span class="text-secondary small">
                                        <i class="bi bi-cash-stack me-1"></i>Float:
                                        <span class="text-secondary">{{ number_format($table->float, 2) }}</span>
                                    </span>
                                @endif
                                @if ($table->shoeType)
                                    <span class="text-secondary small">
                                        <i class="bi bi-stack me-1"></i>Shoe:
                                        <span class="text-warning">{{ $table->shoeType->shoe_name }}</span>
                                    </span>
                                @endif
                            </div>

                            {{-- Config Bets --}}
                            @if ($preset)
                                <div class="d-flex flex-wrap gap-3 mt-2">
                                    @php
                                        $range = $table->active_bet_range;
                                    @endphp
                                    @if (!empty($range))
                                        <span class="text-secondary small">
                                            <i class="bi bi-coin me-1"></i>
                                            Min-Max: <span
                                                class="text-warning">{{ number_format($range['min'], 2) }}</span>
                                            <span class="text-secondary">–</span>
                                            <span class="text-warning">{{ number_format($range['max'], 2) }}</span>
                                            @if (count(explode('|', $table->config?->preset?->min_bet ?? '')) > 1)
                                                <span class="badge bg-secondary ms-1"
                                                    style="font-size:0.65rem;">L{{ $table->bet_index }}</span>
                                            @endif
                                        </span>
                                    @endif
                                    @if (isset($preset->side_min_bet))
                                        <span class="small" style="color:#aaa;">
                                            Side: <span
                                                class="text-warning fw-bold">{{ number_format($preset->side_min_bet, 2) }}
                                                – {{ number_format($preset->side_max_bet, 2) }}</span>
                                        </span>
                                    @endif
                                    @if (isset($preset->tie_min))
                                        <span class="small" style="color:#aaa;">
                                            Tie: <span
                                                class="text-warning fw-bold">{{ number_format($preset->tie_min, 2) }} –
                                                {{ number_format($preset->tie_max, 2) }}</span>
                                        </span>
                                    @endif
                                    @if (isset($preset->commission))
                                        <span class="small" style="color:#aaa;">
                                            Commission:
                                            <span
                                                class="{{ $preset->commission ? 'text-warning' : 'text-secondary' }} fw-bold">
                                                {{ $preset->commission ? 'Enabled (0.95x)' : 'Disabled (1x)' }}
                                            </span>
                                        </span>
                                    @endif
                                    @if (isset($preset->surrender))
                                        <span class="small" style="color:#aaa;">
                                            Surrender:
                                            <span class="text-warning fw-bold">
                                                @if ($preset->surrender == '0')
                                                    No Surrender
                                                @elseif($preset->surrender == '1')
                                                    Surrender on any card
                                                @else
                                                    Surrender on any card except Ace
                                                @endif
                                            </span>
                                        </span>
                                    @endif
                                    @if (isset($preset->insurance))
                                        <span class="small" style="color:#aaa;">
                                            Insurance: <span
                                                class="text-warning fw-bold">{{ $preset->insurance ? 'Enabled' : 'Disabled' }}</span>
                                        </span>
                                    @endif
                                    @if (isset($preset->burn_card))
                                        <span class="small" style="color:#aaa;">
                                            Burn Card: <span
                                                class="text-warning fw-bold">{{ $preset->burn_card }}</span>
                                        </span>
                                    @endif
                                    @if (isset($preset->split_type))
                                        <span class="small" style="color:#aaa;">
                                            Split Type: <span
                                                class="text-warning fw-bold">{{ $preset->split_type === 'same_rank' ? 'Same Rank' : 'Same Value' }}</span>
                                        </span>
                                    @endif
                                    @if (isset($preset->soft17rule))
                                        <span class="small" style="color:#aaa;">
                                            Soft 17 Rule: <span
                                                class="text-warning fw-bold">{{ $preset->soft17rule === 's17' ? 'Stand Soft 17' : 'Hit Soft 17' }}</span>
                                        </span>
                                    @endif

                                </div>

                                {{-- Toggle Badges --}}
                                {{-- <div class="d-flex flex-wrap gap-2 mt-2">
                            @foreach ([
        'enable_pairbets' => 'Pair Bets',
        'enable_lucky6' => 'Lucky 6',
        'enable_super_andar' => 'Super Andar',
        'enable_super_bahar' => 'Super Bahar',
        'enable_777_charlie' => '777 Charlie',
    ] as $field => $label)
                            @if (isset($preset->$field))
                            <span class="badge" style="{{ $preset->$field
                                                        ? 'background:#0f2e1a; color:#6fcf97; border:1px solid #6fcf97;'
                                                        : 'background:#1e1e1e; color:#555; border:1px solid #333;' }}
                        font-size:10px;">
                        {{ $preset->$field ? '✓' : '✕' }} {{ $label }}
                        </span>
                        @endif
                        @endforeach
                    </div> --}}
                            @else
                                <div class="text-muted small mt-2 fst-italic">No configuration assigned</div>
                            @endif
                        </div>

                        {{-- ── RIGHT: Chip Preview ── --}}
                        <div class="col-md-3 d-flex flex-column align-items-center justify-content-center p-3"
                            style="border-left:1px solid #222; border-right:1px solid #222;">
                            @if ($chip)
                                <div class="text-warning small mb-2 fw-bold" style="letter-spacing:0.05em; font-size:10px;">
                                    {{ strtoupper($chip->preset_name) }}
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-wrap justify-content-center">
                                    @foreach ($colors as $i => $color)
                                        @php $val = $chip->{'chip_'.($i+1).'_value'}; @endphp
                                        <div class="text-center">
                                            <div class="casino-chip chip-{{ $color }}"
                                                style="width:44px; height:44px; font-size:10px; border-width:2px;">
                                                <span class="text-white fw-bold"
                                                    style="font-size:10px;">{{ $val }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                    <div
                                        style="width:1px; height:44px; background:linear-gradient(to bottom, transparent, #ffc107, transparent); margin:0 6px;">
                                    </div>
                                    <div class="text-center">
                                        <div class="text-warning" style="font-size:9px; letter-spacing:0.05em;">BASE
                                        </div>
                                        <div class="text-white fw-bold" style="font-size:13px;">
                                            {{ $chip->base_value }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted small fst-italic">No chip preset</span>
                            @endif
                        </div>
                        {{-- ── FAR RIGHT: Actions ── --}}
                        <div class="col-md-2 d-flex flex-column align-items-center justify-content-center gap-2 p-3">

                            @php $isLocked = $table->isFloatOpen(); @endphp

                            @if($isLocked)
                                <button class="btn btn-sm btn-outline-secondary w-100 disabled" 
                                    title="Table is currently open. Close float to edit.">
                                    <i class="bi bi-lock-fill me-1"></i>Locked
                                </button>
                            @else
                                <a href="{{ route('game_tables.edit', $table->id) }}"
                                    class="btn btn-sm btn-outline-warning w-100">
                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                </a>
                            @endif

                            {{-- Unregister MAC --}}
                            @if ($table->active_mac)
                                <form method="POST" action="{{ route('game_tables.unregister-mac', $table->id) }}"
                                    class="w-100">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                                        {{ $isLocked ? 'disabled' : '' }}
                                        onclick="return confirm('Unregister MAC {{ $table->active_mac }} from this table?')">
                                        <i class="bi bi-pc-display me-1"></i>Unregister MAC
                                    </button>
                                </form>
                            @else
                                <span class="btn btn-sm w-100 disabled"
                                    style="border:1px dashed #444; color:#555; font-size:11px;">
                                    <i class="bi bi-pc-display me-1"></i>No MAC Bound
                                </span>
                            @endif

                            {{-- Deactivate / Restore --}}
                            @if ($table->status == 1)
                                <form method="POST" action="{{ route('game_tables.deactivate', $table->id) }}"
                                    class="w-100">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                                        {{ $isLocked ? 'disabled' : '' }}
                                        onclick="return confirm('Deactivate this table?')">
                                        <i class="bi bi-pause-circle me-1"></i>Deactivate
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('game_tables.restore', $table->id) }}"
                                    class="w-100">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- ── PAYOUT RULES ── --}}
                        <div class="col-md-12 px-4 pb-3" style="border-top:1px solid #1e1e1e;">
                            <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                                <span class="text-warning small fw-bold" style="letter-spacing:0.06em;">
                                    PAYOUT RULES
                                </span>
                                <div
                                    style="flex:1; height:1px; background:linear-gradient(to right, #ffc10733, transparent);">
                                </div>
                                {{-- Active/Total count badge --}}
                                @php
                                    $totalRules = $table->payoutRules->count();
                                    $activeRules = $table->payoutRules->where('is_active', 1)->count();
                                @endphp
                                <span class="badge rounded-pill"
                                    style="background:#1a1a1a; color:#ffc107; border:1px solid #ffc10744; font-size:10px;">
                                    {{ $activeRules }}/{{ $totalRules }} active
                                </span>
                            </div>

                            @if ($table->payoutRules->isEmpty())
                                <span class="text-muted small fst-italic">No payout rules assigned</span>
                            @else
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($table->payoutRules as $tableRule)
                                        @php $rule = $tableRule->payoutRule; @endphp
                                        @if ($rule)
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex align-items-center gap-1 px-2 py-1 rounded"
                                                    style="{{ $tableRule->is_active
                                                        ? 'background:#0f1f0f; border:1px solid #2a5a2a;'
                                                        : 'background:#1a1a1a; border:1px solid #2e2e2e;' }}">

                                                    {{-- Active dot --}}
                                                    <span
                                                        style="width:6px; height:6px; border-radius:50%; flex-shrink:0;
                                 background:{{ $tableRule->is_active ? '#6fcf97' : '#555' }};"></span>

                                                    {{-- Bet name --}}
                                                    <span
                                                        style="font-size:11px; color:{{ $tableRule->is_active ? '#ddd' : '#555' }};">
                                                        {{ $rule->bet_name }}
                                                    </span>

                                                    @if ($rule->bet_position)
                                                        <span
                                                            style="font-size:10px; color:#666;">({{ $rule->bet_position }})</span>
                                                    @endif

                                                    {{-- Jackpot badge --}}
                                                    @if ($rule->is_jackpot)
                                                        <span
                                                            style="font-size:9px; padding:1px 5px; border-radius:8px;
                                     background:#1a1200; color:#ffc107; border:1px solid #ffc10744;">
                                                            JACKPOT
                                                        </span>
                                                    @endif

                                                    {{-- Divider --}}
                                                    <span
                                                        style="width:1px; height:12px; background:#333; margin:0 3px;"></span>

                                                    {{-- Multiplier or seed --}}
                                                    @php
                                                        $displayMultiplier = $rule->payout_multiplier;
                                                        if ($table->gameType?->code === 'BAC' && $preset) {
                                                            $commission = $preset->baccarat_6_commission ?? 1;
                                                            if ($rule->bet_position === 'B') {
                                                                $displayMultiplier = $commission ? 0.95 : 1.0;
                                                            } elseif ($rule->bet_position === 'B6') {
                                                                $displayMultiplier = $commission ? 0.95 : 0.5;
                                                            }
                                                        }
                                                    @endphp
                                                    @if ($displayMultiplier)
                                                        <span
                                                            style="font-size:11px; font-weight:bold;
                                     color:{{ $tableRule->is_active ? '#ffc107' : '#444' }};">
                                                            {{ $displayMultiplier }}x
                                                        </span>
                                                    @else
                                                        <span
                                                            style="font-size:11px; font-weight:bold; color:#555;">—</span>
                                                    @endif
                                                </div>

                                                {{-- Seed value pill — only for active jackpot rules with a seed ──────── --}}
                                                @if ($rule->is_jackpot && $tableRule->is_active && $tableRule->seed_value)
                                                    <div class="d-flex align-items-center gap-1 px-2 py-1 rounded"
                                                        style="background:#1a1200; border:1px solid #ffc10733;">
                                                        <i class="bi bi-coin"
                                                            style="color:#ffc107; font-size:10px;"></i>
                                                        <span
                                                            style="font-size:10px; color:#999; letter-spacing:0.04em;">SEED</span>
                                                        <span style="font-size:11px; font-weight:bold; color:#ffc107;">
                                                            {{ number_format($tableRule->seed_value, 2) }}
                                                        </span>
                                                    </div>
                                                @endif

                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>



                    </div>
                </div>
            </div>

        @empty
            <div class="text-center py-5">
                <div style="font-size:48px; opacity:0.2;">🎰</div>
                <div class="text-muted mt-3">No game tables found.</div>
                <a href="{{ route('game_tables.create') }}" class="btn btn-warning btn-sm mt-3">
                    + Create First Table
                </a>
            </div>
        @endforelse

        {{-- Pagination --}}
        @if ($tables->hasPages())
            <div class="d-flex justify-content-center mt-2">
                {{ $tables->links() }}
            </div>
        @endif

    </div>
</x-app-layout>

{{-- ══════════════════════════════════════════
     Overdue Session Bell — Styles & Script
══════════════════════════════════════════ --}}
<style>
/* ── Bell button ── */
.notif-bell-btn {
    background: transparent;
    cursor: pointer;
    outline: none;
    line-height: 1;
}
.notif-bell-icon {
    font-size: 22px;
    color: #ffc107;
    animation: bellRing 1.4s ease-in-out infinite;
    display: block;
    filter: drop-shadow(0 0 6px #ffc10788);
}
@keyframes bellRing {
    0%,100% { transform: rotate(0deg); }
    10%      { transform: rotate(14deg); }
    20%      { transform: rotate(-12deg); }
    30%      { transform: rotate(10deg); }
    40%      { transform: rotate(-8deg); }
    50%      { transform: rotate(5deg); }
    60%      { transform: rotate(0deg); }
}

/* ── Badge ── */
.notif-badge {
    position: absolute;
    top: -5px;
    right: -7px;
    background: #e53935;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    min-width: 17px;
    height: 17px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    line-height: 1;
    border: 1.5px solid #111;
    box-shadow: 0 0 6px #e5393588;
    pointer-events: none;
}

/* ── Panel ── */
.notif-panel {
    position: absolute;
    top: calc(100% + 12px);
    left: 50%;
    transform: translateX(-50%);
    width: 340px;
    background: #141414;
    border: 1px solid #ffc10744;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.7), 0 0 0 1px #ffc10722;
    z-index: 1055;
    animation: panelFadeIn 0.18s ease;
    overflow: hidden;
}
@keyframes panelFadeIn {
    from { opacity: 0; transform: translateX(-50%) translateY(-6px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}
.notif-panel-header {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    border-bottom: 1px solid #2a2a2a;
    font-size: 12px;
    font-weight: 600;
    color: #ffc107;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.notif-close-btn {
    background: transparent;
    border: none;
    color: #666;
    font-size: 12px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}
.notif-close-btn:hover { color: #fff; }

/* ── Panel body & items ── */
.notif-panel-body {
    max-height: 320px;
    overflow-y: auto;
    padding: 8px 0;
}
.notif-panel-body::-webkit-scrollbar { width: 4px; }
.notif-panel-body::-webkit-scrollbar-track { background: transparent; }
.notif-panel-body::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }

.notif-item {
    padding: 10px 14px;
    border-bottom: 1px solid #1e1e1e;
    transition: background 0.15s;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #1a1a1a; }

.notif-item-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 5px;
}
.notif-table-name {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    flex: 1;
}
.notif-duration-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    background: #3b1a00;
    color: #ff9800;
    border: 1px solid #ff980055;
    white-space: nowrap;
}

.notif-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 10px;
    color: #777;
    margin-bottom: 6px;
}
.notif-item-warning {
    font-size: 10px;
    color: #e57373;
    background: #2e0d0d;
    border: 1px solid #e5373333;
    border-radius: 6px;
    padding: 4px 8px;
    line-height: 1.4;
}

/* ── Footer ── */
.notif-panel-footer {
    padding: 8px 14px;
    border-top: 1px solid #2a2a2a;
    font-size: 10px;
    color: #666;
    text-align: center;
}
</style>

<script>
function toggleNotifPanel() {
    const panel = document.getElementById('notif-panel');
    if (!panel) return;
    panel.classList.toggle('d-none');
}

// Close panel when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notif-bell-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        const panel = document.getElementById('notif-panel');
        if (panel) panel.classList.add('d-none');
    }
});
</script>
