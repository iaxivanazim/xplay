<x-app-layout>
<div class="content-wrapper p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="text-warning mb-0">
                <i class="bi bi-pencil-square me-2"></i>Edit Tab — <span class="text-white">{{ $tab->table_name }}</span>
            </h5>
            <div class="mt-1" style="font-size:12px; color:#666;">
                ID: {{ $tab->id }} &nbsp;|&nbsp;
                Game: <span class="text-warning fw-bold">{{ $tab->gameType?->name }} ({{ $tab->gameType?->code }})</span> &nbsp;|&nbsp;
                Status:
                @php $ts = $tab->tabStatus; @endphp
                @if($tab->hasActiveSession())
                    <span style="color:#6fcf97; font-weight:bold;">IN SESSION (OTP: {{ $ts?->current_otp }})</span>
                @elseif(!$tab->isEnabled())
                    <span style="color:#eb5757; font-weight:bold;">DISABLED</span>
                @elseif($tab->isLocked())
                    <span style="color:#ff9800; font-weight:bold;">LOCKED</span>
                @else
                    <span style="color:#aaa;">IDLE</span>
                @endif
            </div>
        </div>
        <a href="{{ route('tabs.index') }}" class="btn btn-outline-warning btn-sm">← Back to Tabs</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 mb-4 py-2"
            style="background:#0f2e1a; color:#6fcf97; font-size:13px;">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 mb-4 py-2"
            style="background:#2e0d0d; color:#eb5757; font-size:13px;">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger border-0 mb-4 py-2" style="background:#2e0d0d; color:#eb5757; font-size:13px;">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Active session warning --}}
    @if($tab->hasActiveSession())
        <div class="alert border-0 mb-4 py-2" style="background:#1a1200; color:#ffc107; border:1px solid #ffc10744 !important; font-size:13px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            This tab has an <strong>active session</strong> (OTP: <code>{{ $ts?->current_otp }}</code>,
            Balance: <strong>${{ number_format((float)$ts?->current_balance, 2) }}</strong>).
            Core config fields are locked. Process a <strong>Cashout</strong> first to unlock editing.
        </div>
    @endif

    <div class="row g-4">

        {{-- ── LEFT: Main Config Form ── --}}
        <div class="col-lg-8">

            <form method="POST" action="{{ route('tabs.update', $tab->id) }}" id="masterForm">
                @csrf
                @method('PUT')

                @php
                    $config = $tab->config;
                    $preset = $config?->preset;
                    $code = $tab->gameType?->code;
                    $colors = ['red', 'blue', 'green', 'purple', 'gold'];
                @endphp

                {{-- ═══════════════════════════════════════ --}}
                {{-- SECTION 1: TAB DETAILS                  --}}
                {{-- ═══════════════════════════════════════ --}}
                <div class="card bg-black border-warning mb-4">
                    <div class="card-body">
                        <h6 class="text-warning mb-3">① Tab Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="text-light small">Tab Name <span class="text-danger">*</span></label>
                                <input type="text" name="table_name" id="tableName"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('table_name', $tab->table_name) }}"
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }} required>
                            </div>

                            <div class="col-md-4">
                                <label class="text-light small">Game Type</label>
                                <input type="text" class="form-control bg-black border-secondary text-warning fw-bold"
                                    value="{{ $tab->gameType?->name }} ({{ $code }})" disabled>
                                <input type="hidden" name="game_type_id" id="gameTypeSelect" value="{{ $tab->game_type_id }}" data-code="{{ $code }}">
                            </div>

                            <div class="col-md-4">
                                <label class="text-light small">
                                    Active MAC Address
                                    <span class="ms-1" style="color:#555; font-size:10px; letter-spacing:0.04em;">
                                        AUTO-REGISTERED / DEVICE
                                    </span>
                                </label>
                                <div class="position-relative">
                                    <input type="text" class="form-control border-secondary pe-5"
                                        style="{{ $tab->active_mac
                                            ? 'background:#0a1f0a; color:#6fcf97;'
                                            : 'background:#111; color:#555;' }}"
                                        name="active_mac"
                                        value="{{ old('active_mac', $tab->active_mac ?? '') }}"
                                        placeholder="Not registered"
                                        {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                    <span class="position-absolute top-50 end-0 translate-middle-y me-3"
                                        style="width:8px; height:8px; border-radius:50%;
                                        background:{{ $tab->active_mac ? '#6fcf97' : '#555' }};
                                        box-shadow:{{ $tab->active_mac ? '0 0 6px #6fcf97' : 'none' }};">
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="text-light small">Denomination</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-black border-secondary text-warning">$</span>
                                    <input type="number" step="0.0001" min="0.0001" name="denomination"
                                        class="form-control bg-black text-white border-secondary"
                                        value="{{ old('denomination', $tab->denomination) }}"
                                        {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="text-light small">Bet Index</label>
                                <select name="bet_index" class="form-select bg-black text-white border-secondary">
                                    @for ($i = 1; $i <= 9; $i++)
                                        <option value="{{ $i }}" {{ old('bet_index', $tab->bet_index) == $i ? 'selected' : '' }}>
                                            Level {{ $i }}
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            <input type="hidden" name="float" value="{{ old('float', $tab->float ?? 0) }}">

                            {{-- Active bet range preview --}}
                            @php $range = $tab->activeBetRange; @endphp
                            @if(!empty($range) && ($range['min'] > 0 || $range['max'] > 0))
                                <div class="col-12">
                                    <div class="px-3 py-2 rounded" style="background:#0d1117; border:1px solid #1e2a3a; font-size:12px; color:#aaa;">
                                        <i class="bi bi-coin me-1 text-warning"></i>
                                        Active Bet Range:
                                        <span class="text-warning fw-bold ms-1">
                                            ${{ number_format($range['min'], 2) }} – ${{ number_format($range['max'], 2) }}
                                        </span>
                                        <span class="text-secondary ms-2">(Level {{ $tab->bet_index }})</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════ --}}
                {{-- SECTION 2: CHIP PRESET                  --}}
                {{-- ═══════════════════════════════════════ --}}
                <div class="card bg-black border-warning mb-4">
                    <div class="card-body">
                        <h6 class="text-warning mb-3">② Chip Preset</h6>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <label class="text-light small">Select Chip Preset <span class="text-danger">*</span></label>
                                <select name="chip_preset_id" id="chipPresetSelect"
                                    class="form-select bg-black text-white border-secondary" required
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                    <option value="">-- Select Preset --</option>
                                    @foreach ($chipPresets as $chip)
                                        <option value="{{ $chip->id }}"
                                            data-chips="{{ json_encode([$chip->chip_1_value, $chip->chip_2_value, $chip->chip_3_value, $chip->chip_4_value, $chip->chip_5_value]) }}"
                                            data-base="{{ $chip->base_value }}"
                                            {{ old('chip_preset_id', $preset?->chip_preset_id) == $chip->id ? 'selected' : '' }}>
                                            {{ $chip->preset_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Live chip preview --}}
                            <div class="col-md-8">
                                <div class="row text-center align-items-center" id="chipPreview">
                                    @foreach ($colors as $i => $color)
                                        <div class="col-auto">
                                            <div class="casino-chip chip-{{ $color }}"
                                                style="width:60px; height:60px;">
                                                <span class="chip-preview-val text-white fw-bold" style="font-size:14px;">
                                                    {{ $preset?->chipPreset?->{'chip_' . ($i + 1) . '_value'} ?? '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="col-auto px-2">
                                        <div
                                            style="width:1px; height:60px; background:linear-gradient(to bottom, transparent, #ffc107, transparent);">
                                        </div>
                                    </div>
                                    <div class="col-auto text-center">
                                        <label class="text-warning small d-block"
                                            style="letter-spacing:0.05em;">BASE</label>
                                        <span id="basePreview" class="text-white fw-bold">
                                            {{ $preset?->chipPreset?->base_value ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════ --}}
                {{-- SECTION 3: GAME CONFIG                  --}}
                {{-- ═══════════════════════════════════════ --}}
                <div class="card bg-black border-warning mb-4" id="gameConfigSection">
                    <div class="card-body">
                        <h6 class="text-warning mb-3">③ Game Configuration</h6>

                        {{-- Common fields --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="text-light small">Config Preset Name <span class="text-danger">*</span></label>
                                <input type="text" name="config[name]" id="configName"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.name', $preset?->name) }}" required
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">
                                    Min Bet <span class="text-danger">*</span>
                                    <span class="ms-1" style="color:#555; font-size:10px;">SEPARATE WITH |</span>
                                </label>
                                <input type="text" name="config[min_bet]" id="minBet"
                                    class="form-control bg-black text-white border-secondary"
                                    placeholder="e.g. 100|200|500" value="{{ old('config.min_bet', $preset?->min_bet) }}"
                                    required {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                <div id="minBetPreview" class="mt-1 d-flex flex-wrap gap-1"></div>
                            </div>

                            <div class="col-md-3">
                                <label class="text-light small">
                                    Max Bet <span class="text-danger">*</span>
                                    <span class="ms-1" style="color:#555; font-size:10px;">SEPARATE WITH |</span>
                                </label>
                                <input type="text" name="config[max_bet]" id="maxBet"
                                    class="form-control bg-black text-white border-secondary"
                                    placeholder="e.g. 1000|2000|5000"
                                    value="{{ old('config.max_bet', $preset?->max_bet) }}" required
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                <div id="maxBetPreview" class="mt-1 d-flex flex-wrap gap-1"></div>
                            </div>
                            <div class="col-md-3" id="burnCardCol" style="{{ $code === 'ROL' ? 'display:none;' : '' }}">
                                <label class="text-light small" id="burnCardLabel">{{ $code === 'AB' ? 'Reset Threshold' : 'Burn Card every round' }}</label>
                                <input type="number" name="config[burn_card]" id="burnCard"
                                    class="form-control bg-black text-white border-secondary" min="0"
                                    max="10" value="{{ old('config.burn_card', $preset?->burn_card) }}"
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                            </div>
                        </div>

                        {{-- ── BACCARAT ── --}}
                        @if ($code === 'BAC')
                            <div class="game-fields" id="fields-BAC">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="text-light small">Side Min Bet</label>
                                        <input type="number" step="0.01" name="config[side_min_bet]" id="sideMinBet"
                                            class="form-control bg-black text-white border-secondary"
                                            value="{{ old('config.side_min_bet', $preset?->side_min_bet) }}"
                                            {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-light small">Side Max Bet</label>
                                        <input type="number" step="0.01" name="config[side_max_bet]" id="sideMaxBet"
                                            class="form-control bg-black text-white border-secondary"
                                            value="{{ old('config.side_max_bet', $preset?->side_max_bet) }}"
                                            {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-light small d-flex align-items-center gap-2">
                                            Commission
                                            @php $b6Active = $payoutRules->firstWhere('bet_position', 'B6')?->is_active ?? false; @endphp
                                            <span id="b6CommissionBadge"
                                                style="font-size:9px; padding:2px 6px; border-radius:10px;
                                                {{ $b6Active
                                                    ? 'background:#0f2e1a; color:#6fcf97; border:1px solid #6fcf97;'
                                                    : 'background:#2e1010; color:#eb5757; border:1px solid #eb5757;' }}">
                                                {{ $b6Active ? 'B6 Active' : 'B6 Inactive' }}
                                            </span>
                                        </label>
                                        <select name="config[baccarat_6_commission]" id="b6CommissionSelect"
                                            class="form-select border-secondary"
                                            style="{{ $b6Active
                                                ? 'background:#0a1a0a; color:#fff; cursor:pointer;'
                                                : 'background:#111; color:#555; cursor:not-allowed;' }}"
                                            {{ $b6Active && !$tab->hasActiveSession() ? '' : 'disabled' }}>
                                            <option value="1"
                                                {{ old('config.baccarat_6_commission', $preset?->baccarat_6_commission ?? 1) ? 'selected' : '' }}>
                                                Commission
                                            </option>
                                            <option value="0"
                                                {{ !old('config.baccarat_6_commission', $preset?->baccarat_6_commission ?? 1) ? 'selected' : '' }}>
                                                Non-Commission
                                            </option>
                                        </select>
                                        <div class="mt-1 d-flex flex-column gap-1" style="font-size:10px; color:#ffc107;">
                                            <span>
                                                <i class="bi bi-info-circle me-1"></i>Banker:
                                                <span id="bankerMultiplier">
                                                    @if ($b6Active)
                                                        {{ (old('config.baccarat_6_commission', $preset?->baccarat_6_commission ?? 1)) ? '0.95x' : '1x' }}
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </span>
                                            <span>
                                                <i class="bi bi-info-circle me-1"></i>B6:
                                                <span id="b6Multiplier">
                                                    @if ($b6Active)
                                                        {{ (old('config.baccarat_6_commission', $preset?->baccarat_6_commission ?? 1)) ? '0.95x' : '0.50x' }}
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ── ANDAR BAHAR ── --}}
                        @if ($code === 'AB')
                            <div class="game-fields" id="fields-AB">
                                {{-- Common min/max and Reset Threshold fields above --}}
                            </div>
                        @endif

                        {{-- ── ROULETTE ── --}}
                        @if ($code === 'ROL')
                            <div class="game-fields" id="fields-ROL">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="text-light small">Roulette Type</label>
                                        <select name="config[roulette_type]" id="rouletteType"
                                            class="form-select bg-black text-white border-secondary"
                                            {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                            <option value="european" {{ old('config.roulette_type', $preset?->roulette_type ?? 'european') == 'european' ? 'selected' : '' }}>
                                                European (Single Zero 0)
                                            </option>
                                            <option value="american" {{ old('config.roulette_type', $preset?->roulette_type ?? 'european') == 'american' ? 'selected' : '' }}>
                                                American (Double Zero 0, 00)
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-light small">Side Min Bet</label>
                                        <input type="number" step="0.01" name="config[side_min_bet]" id="sideMinBetROL"
                                            class="form-control bg-black text-white border-secondary"
                                            value="{{ old('config.side_min_bet', $preset?->side_min_bet) }}"
                                            placeholder="Optional" {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-light small">Side Max Bet</label>
                                        <input type="number" step="0.01" name="config[side_max_bet]" id="sideMaxBetROL"
                                            class="form-control bg-black text-white border-secondary"
                                            value="{{ old('config.side_max_bet', $preset?->side_max_bet) }}"
                                            placeholder="Optional" {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>

                {{-- ═══════════════════════════════════════ --}}
                {{-- SECTION 4: PAYOUT RULES                 --}}
                {{-- ═══════════════════════════════════════ --}}
                <div class="card bg-black border-warning mb-4" id="payoutSection">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-warning mb-0">④ Payout Rules</h6>
                            <span class="badge rounded-pill" style="background:#1a1a1a; color:#ffc107; border:1px solid #ffc10744; font-size:10px;">
                                {{ $payoutRules->where('is_active', 1)->count() }}/{{ $payoutRules->count() }} active
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-bordered text-center align-middle">
                                <thead>
                                    <tr class="text-warning">
                                        <th>Bet Name</th>
                                        <th>Position</th>
                                        <th>Payout Multiplier</th>
                                        <th>Active</th>
                                        <th>Seed Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payoutRules as $rule)
                                        <tr>
                                            <td class="text-light">
                                                {{ $rule->bet_name }}
                                                @if ($rule->is_jackpot)
                                                    <span class="ms-1 badge"
                                                        style="background:#1a1200; color:#ffc107;
                                                               border:1px solid #ffc10744; font-size:9px;">
                                                        JACKPOT
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-light">{{ $rule->bet_position ?? '—' }}</td>
                                            <td class="text-warning fw-bold">
                                                @php
                                                    $multiplier = $rule->payout_multiplier;
                                                    if ($code === 'BAC') {
                                                        $commission = old('config.baccarat_6_commission', $preset?->baccarat_6_commission ?? 1);
                                                        if ($rule->bet_position === 'B') {
                                                            $multiplier = $commission ? 0.95 : 1.0;
                                                        } elseif ($rule->bet_position === 'B6') {
                                                            $multiplier = $commission ? 0.95 : 0.5;
                                                        }
                                                    }
                                                @endphp
                                                {{ $multiplier ? $multiplier . 'x' : '—' }}
                                            </td>
                                            <td>
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input payout-toggle" type="checkbox"
                                                        name="payout_overrides[{{ $rule->payout_id }}]" value="1"
                                                        data-position="{{ $rule->bet_position }}"
                                                        data-jackpot="{{ $rule->is_jackpot ? '1' : '0' }}"
                                                        data-payout-id="{{ $rule->payout_id }}"
                                                        {{ $rule->is_active ? 'checked' : '' }}
                                                        {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($rule->is_jackpot)
                                                    <input type="number" step="0.01" min="0"
                                                        name="seed_values[{{ $rule->payout_id }}]"
                                                        id="seed_{{ $rule->payout_id }}"
                                                        class="form-control form-control-sm text-center seed-input"
                                                        style="{{ $rule->is_active
                                                            ? 'background:#0a1a0a; color:#ffc107; border:1px solid #ffc10755; width:120px; margin:auto;'
                                                            : 'background:#111; color:#555; border:1px solid #333; width:120px; margin:auto; cursor:not-allowed;' }}"
                                                        value="{{ old('seed_values.' . $rule->payout_id, $rule->seed_value ?? '') }}"
                                                        placeholder="Enter seed"
                                                        {{ $rule->is_active && !$tab->hasActiveSession() ? '' : 'disabled' }}>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-muted fst-italic">
                                                No payout rules defined for this game type.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- SUBMIT --}}
                <div class="d-flex gap-3 mb-5">
                    <button type="submit" class="btn btn-warning fw-bold px-5"
                        {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                    <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                </div>

            </form>

        </div>

        {{-- ── RIGHT: State management sidebar ── --}}
        <div class="col-lg-4">

            {{-- Live status card --}}
            <div class="card border-0 mb-3" style="background:#111;">
                <div class="card-body">
                    <h6 class="text-warning mb-3" style="letter-spacing:0.06em; font-size:12px;">LIVE STATUS</h6>

                    <div class="d-flex justify-content-between mb-2" style="font-size:12px;">
                        <span class="text-secondary">Connection:</span>
                        <span style="color: {{ ($ts?->connection_status ?? 'offline') === 'online' ? '#6fcf97' : '#eb5757' }}; font-weight:bold;">
                            {{ strtoupper($ts?->connection_status ?? 'offline') }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2" style="font-size:12px;">
                        <span class="text-secondary">Balance:</span>
                        <span class="text-warning fw-bold">${{ number_format((float)$ts?->current_balance, 2) }}</span>
                    </div>
                    @if($ts?->current_otp)
                        <div class="d-flex justify-content-between mb-2" style="font-size:12px;">
                            <span class="text-secondary">OTP:</span>
                            <code style="color:#6fcf97; background:#0f2e1a; padding:2px 8px; border-radius:4px; font-size:14px; letter-spacing:0.15em;">
                                {{ $ts->current_otp }}
                            </code>
                        </div>
                    @endif
                    @if($ts?->session_started_at)
                        <div class="d-flex justify-content-between mb-2" style="font-size:12px;">
                            <span class="text-secondary">Session start:</span>
                            <span class="text-white">{{ $ts->session_started_at->format('H:i:s') }}</span>
                        </div>
                    @endif
                    @if($ts?->last_synced_at)
                        <div class="d-flex justify-content-between" style="font-size:11px;">
                            <span class="text-secondary">Last sync:</span>
                            <span style="color:#555;">{{ $ts->last_synced_at->diffForHumans() }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- MAC Address management --}}
            <div class="card border-0 mb-3" style="background:#111;">
                <div class="card-body">
                    <h6 class="text-warning mb-3" style="letter-spacing:0.06em; font-size:12px;">HARDWARE BINDING</h6>

                    @if($tab->active_mac)
                        <div class="mb-3 px-2 py-2 rounded d-flex align-items-center justify-content-between"
                            style="background:#0f2e1a; border:1px solid #2a5a2a;">
                            <div>
                                <div style="font-size:10px; color:#6fcf97; letter-spacing:0.06em;">BOUND MAC</div>
                                <code style="color:#fff; font-size:13px;">{{ $tab->active_mac }}</code>
                            </div>
                            <form method="POST" action="{{ route('tabs.unregister-mac', $tab->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }}
                                    onclick="return confirm('Unregister MAC?')">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="mb-3 px-2 py-2 rounded text-center"
                            style="background:#1a1a1a; border:1px dashed #333; font-size:12px; color:#555;">
                            <i class="bi bi-pc-display me-1"></i>No MAC registered
                        </div>
                    @endif

                    <form method="POST" action="{{ route('tabs.register-mac', $tab->id) }}">
                        @csrf
                        <div class="input-group input-group-sm">
                            <input type="text" name="mac_address"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="AA:BB:CC:DD:EE:FF"
                                pattern="^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$"
                                required>
                            <button type="submit" class="btn btn-outline-warning" style="font-size:12px;">
                                <i class="bi bi-link-45deg me-1"></i>Bind
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Enable / Disable --}}
            <div class="card border-0 mb-3" style="background:#111;">
                <div class="card-body">
                    <h6 class="text-warning mb-3" style="letter-spacing:0.06em; font-size:12px;">TAB STATE</h6>

                    <div class="d-grid gap-2">
                        @if($tab->isEnabled())
                            <form method="POST" action="{{ route('tabs.disable', $tab->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }}
                                    onclick="return confirm('Disable this tab?')">
                                    <i class="bi bi-slash-circle me-1"></i>Disable Tab
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('tabs.enable', $tab->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                    <i class="bi bi-check-circle me-1"></i>Enable Tab
                                </button>
                            </form>
                        @endif

                        @if($tab->lock_status === 'unlocked')
                            <form method="POST" action="{{ route('tabs.lock', $tab->id) }}">
                                @csrf
                                <input type="hidden" name="lock_status" value="locked">
                                <button type="submit" class="btn btn-outline-warning btn-sm w-100">
                                    <i class="bi bi-lock me-1"></i>Lock Tab
                                </button>
                            </form>
                            <form method="POST" action="{{ route('tabs.lock', $tab->id) }}">
                                @csrf
                                <input type="hidden" name="lock_status" value="break">
                                <button type="submit" class="btn btn-sm w-100" style="border:1px solid #7986cb; color:#7986cb;">
                                    <i class="bi bi-pause-circle me-1"></i>Put on Break
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('tabs.unlock', $tab->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                    <i class="bi bi-unlock me-1"></i>Unlock Tab
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="card border-0" style="background:#111; border:1px solid #2e0d0d !important;">
                <div class="card-body">
                    <h6 class="mb-3" style="color:#eb5757; letter-spacing:0.06em; font-size:12px;">DANGER ZONE</h6>
                    <form method="POST" action="{{ route('tabs.destroy', $tab->id) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                            {{ $tab->hasActiveSession() ? 'disabled' : '' }}
                            onclick="return confirm('PERMANENTLY DELETE tab {{ $tab->table_name }}?\nAll session and ledger history will also be removed. This cannot be undone.')">
                            <i class="bi bi-trash me-1"></i>Delete This Tab
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>
</x-app-layout>
