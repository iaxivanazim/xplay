<x-app-layout>
    <div class="content-wrapper p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="text-warning mb-0">Edit Game Table</h5>
                <small class="text-muted">{{ $gameTable->table_name }}
                    &nbsp;·&nbsp;
                    <span
                        style="color:{{ match ($gameTable->gameType?->code ?? '') {
                            'baccarat' => '#c9a227',
                            'blackjack' => '#0047ab',
                            'dragontiger' => '#b30000',
                            'andarbahar' => '#006400',
                            'threecardpoker' => '#5a007a',
                            'miniflush' => '#1a6b6b',
                            'casinowar' => '#8b0000',
                            default => '#aaa',
                        } }}">
                        {{ $gameTable->gameType?->name }}
                    </span>
                </small>
            </div>
            <a href="{{ route('game_tables.index') }}" class="btn btn-outline-warning btn-sm">← Back</a>
        </div>

        @if (session('success'))
            <div class="alert border-0 mb-4 alert-dismissible fade show" style="background:#0f2e1a; color:#6fcf97;">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('game_tables.update', $gameTable->id) }}" id="masterForm">
            @csrf
            @method('PUT')

            @php
                $config = $gameTable->config;
                $preset = $config?->preset;
                $code = $gameTable->gameType?->code;
                $colors = ['red', 'blue', 'green', 'purple', 'gold'];
            @endphp

            {{-- ═══════════════════════════════════════ --}}
            {{-- SECTION 1: TABLE DETAILS                --}}
            {{-- ═══════════════════════════════════════ --}}
            <div class="card bg-black border-warning mb-4">
                <div class="card-body">
                    <h6 class="text-warning mb-3">① Table Details</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-light small">Table Name</label>
                            <input type="text" name="table_name" id="tableName"
                                class="form-control bg-black text-white border-secondary"
                                value="{{ old('table_name', $gameTable->table_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">Game Type</label>
                            <input type="text" class="form-control bg-black border-secondary"
                                style="color:{{ match ($code ?? '') {
                                    'baccarat' => '#c9a227',
                                    'blackjack' => '#0047ab',
                                    'dragontiger' => '#b30000',
                                    'andarbahar' => '#006400',
                                    'threecardpoker' => '#5a007a',
                                    'miniflush' => '#1a6b6b',
                                    'casinowar' => '#8b0000',
                                    default => '#aaa',
                                } }}"
                                value="{{ $gameTable->gameType?->name }}" disabled>
                            {{-- game_type_id is fixed after creation --}}
                            <input type="hidden" name="game_type_id" value="{{ $gameTable->game_type_id }}">
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">
                                Active MAC Address
                                <span class="ms-1" style="color:#555; font-size:10px; letter-spacing:0.04em;">
                                    AUTO-REGISTERED BY DEVICE
                                </span>
                            </label>
                            <div class="position-relative">
                                <input type="text" class="form-control border-secondary pe-5"
                                    style="{{ $gameTable->active_mac
                                        ? 'background:#0a1f0a; color:#6fcf97; cursor:not-allowed;'
                                        : 'background:#111; color:#555; cursor:not-allowed;' }}"
                                    value="{{ $gameTable->active_mac ?? 'Not yet registered' }}" disabled>
                                {{-- Status dot --}}
                                <span class="position-absolute top-50 end-0 translate-middle-y me-3"
                                    style="width:8px; height:8px; border-radius:50%;
                     background:{{ $gameTable->active_mac ? '#6fcf97' : '#555' }};
                     box-shadow:{{ $gameTable->active_mac ? '0 0 6px #6fcf97' : 'none' }};">
                                </span>
                            </div>
                            @if ($gameTable->active_mac)
                                <div class="mt-1" style="font-size:10px; color:#6fcf97;">
                                    <i class="bi bi-check-circle me-1"></i>Device bound
                                </div>
                            @else
                                <div class="mt-1" style="font-size:10px; color:#555;">
                                    <i class="bi bi-circle me-1"></i>Awaiting device registration
                                </div>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">
                                Float Reference
                                <span class="ms-1" style="color:#555; font-size:10px;">SUGGESTED OPENING FLOAT</span>
                            </label>
                            <input type="number" step="0.01" name="float"
                                class="form-control bg-black text-white border-secondary"
                                value="{{ old('float', $gameTable->float) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">Felt Color</label>
                            <div class="d-flex gap-2 align-items-center mt-1">
                                <input type="color" name="felt_color" id="feltColor"
                                    class="form-control form-control-color border-secondary"
                                    value="{{ old('felt_color', $gameTable->felt_color ?? '#006400') }}"
                                    style="width:50px; height:38px; background:transparent;">
                                <span id="feltColorLabel" class="text-light small">
                                    {{ old('felt_color', $gameTable->felt_color ?? '#006400') }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">Shoe Type</label>
                            <select name="shoe_type_id" id="shoeTypeSelect"
                                class="form-select bg-black text-white border-secondary">
                                <option value="">-- Select Shoe Type --</option>
                                @foreach ($shoeTypes as $shoe)
                                    <option value="{{ $shoe->id }}"
                                        {{ old('shoe_type_id', $gameTable->shoe_type_id) == $shoe->id ? 'selected' : '' }}>
                                        {{ $shoe->shoe_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
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
                            <label class="text-light small">Select Chip Preset</label>
                            <select name="chip_preset_id" id="chipPresetSelect"
                                class="form-select bg-black text-white border-secondary" required>
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
            <div class="card bg-black border-warning mb-4">
                <div class="card-body">
                    <h6 class="text-warning mb-3">③ Game Configuration</h6>

                    {{-- Common fields --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="text-light small">Config Preset Name</label>
                            <input type="text" name="config[name]"
                                class="form-control bg-black text-white border-secondary"
                                value="{{ old('config.name', $preset?->name) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="text-light small">
                                Min Bet
                                <span class="ms-1" style="color:#555; font-size:10px;">SEPARATE MULTIPLE WITH
                                    |</span>
                            </label>
                            <input type="text" name="config[min_bet]" id="minBet"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. 100|200|500" value="{{ old('config.min_bet', $preset?->min_bet) }}"
                                required>
                            <div id="minBetPreview" class="mt-1 d-flex flex-wrap gap-1"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="text-light small">
                                Max Bet
                                <span class="ms-1" style="color:#555; font-size:10px;">SEPARATE MULTIPLE WITH
                                    |</span>
                            </label>
                            <input type="text" name="config[max_bet]" id="maxBet"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. 1000|2000|5000"
                                value="{{ old('config.max_bet', $preset?->max_bet) }}" required>
                            <div id="maxBetPreview" class="mt-1 d-flex flex-wrap gap-1"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="text-light small">{{ $code === 'AB' ? 'Reset Threshold' : 'Burn Card every round' }}</label>
                            <input type="number" name="config[burn_card]" id="burnCard"
                                class="form-control bg-black text-white border-secondary" min="0"
                                max="10" value="{{ old('config.burn_card', $preset?->burn_card) }}">
                        </div>

                    </div>

                    {{-- ── BACCARAT ── --}}
                    @if ($code === 'BAC')
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Side Min Bet</label>
                                <input type="number" step="0.01" name="config[side_min_bet]" id="sideMinBet"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.side_min_bet', $preset?->side_min_bet) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Side Max Bet</label>
                                <input type="number" step="0.01" name="config[side_max_bet]" id="sideMaxBet"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.side_max_bet', $preset?->side_max_bet) }}">
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
                                    {{ $b6Active ? '' : 'disabled' }}>
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
                                                {{ $preset?->baccarat_6_commission ?? 1 ? '0.95x' : '1x' }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </span>
                                    <span>
                                        <i class="bi bi-info-circle me-1"></i>B6:
                                        <span id="b6Multiplier">
                                            @if ($b6Active)
                                                {{ $preset?->baccarat_6_commission ?? 1 ? '0.95x' : '0.50x' }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </span>
                                </div>
                            </div>
                            {{-- <div class="col-md-3 d-flex flex-column justify-content-center gap-2 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="config[enable_pairbets]" value="1" id="enablePairbets" {{ old('config.enable_pairbets', $preset?->enable_pairbets) ? 'checked' : '' }}>
                        <label class="form-check-label text-light" for="enablePairbets">Pair Bets</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="config[enable_lucky6]" value="1" id="enableLucky6" {{ old('config.enable_lucky6', $preset?->enable_lucky6) ? 'checked' : '' }}>
                        <label class="form-check-label text-light" for="enableLucky6">Lucky 6</label>
                    </div>
                </div> --}}
                        </div>
                    @endif

                    {{-- ── ANDAR BAHAR ── --}}
                    {{-- @if ($code === 'AB')
                    <div class="row g-3">
                        <div class="col-md-6 d-flex flex-column justify-content-center gap-2 mt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="config[enable_super_andar]" value="1" id="enableSuperAndar" {{ old('config.enable_super_andar', $preset?->enable_super_andar) ? 'checked' : '' }}>
            <label class="form-check-label text-light" for="enableSuperAndar">Super Andar</label>
    </div>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="config[enable_super_bahar]" value="1" id="enableSuperBahar" {{ old('config.enable_super_bahar', $preset?->enable_super_bahar) ? 'checked' : '' }}>
        <label class="form-check-label text-light" for="enableSuperBahar">Super Bahar</label>
    </div>
    </div>
    </div>
    @endif --}}

                    {{-- ── DRAGON TIGER ── --}}
                    @if ($code === 'DT')
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Tie Min</label>
                                <input type="number" step="0.01" name="config[tie_min]" id="tieMin"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.tie_min', $preset?->tie_min) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Tie Max</label>
                                <input type="number" step="0.01" name="config[tie_max]" id="tieMax"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.tie_max', $preset?->tie_max) }}">
                            </div>
                        </div>
                    @endif

                    {{-- ── THREE CARD POKER ── --}}
                    @if ($code === '3CP')
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Side Min</label>
                                <input type="number" step="0.01" name="config[side_min]" id="sideMin"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.side_min', $preset?->side_min) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Side Max</label>
                                <input type="number" step="0.01" name="config[side_max]" id="sideMax"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.side_max', $preset?->side_max) }}">
                            </div>
                            {{-- <div class="col-md-3">
                            <label class="text-light small">Six Card Bonus</label>
                            <input type="number" step="0.01" name="config[six_card_bonus]" class="form-control bg-black text-white border-secondary" value="{{ old('config.six_card_bonus', $preset?->six_card_bonus) }}">
    </div> --}}
                        </div>
                    @endif

                    {{-- ── BLACKJACK ── --}}
                    @if ($code === 'BJ')
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Pair Min</label>
                                <input type="number" step="0.01" name="config[pair_min]" id="pairMin"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.pair_min', $preset?->pair_min) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Pair Max</label>
                                <input type="number" step="0.01" name="config[pair_max]" id="pairMax"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.pair_max', $preset?->pair_max) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Surrender Option</label>
                                <select name="config[surrender]"
                                    class="form-select bg-black text-white border-secondary">
                                    <option value="">-- Select --</option>
                                    <option value="0"
                                        {{ old('config.surrender', $preset?->surrender) === '0' ? 'selected' : '' }}>No
                                        Surrender</option>
                                    <option value="1"
                                        {{ old('config.surrender', $preset?->surrender) === '1' ? 'selected' : '' }}>
                                        Surrender on any card</option>
                                    <option value="2"
                                        {{ old('config.surrender', $preset?->surrender) === '2' ? 'selected' : '' }}>
                                        Surrender on any card except Ace</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Insurance</label>
                                <select name="config[insurance]"
                                    class="form-select bg-black text-white border-secondary">
                                    <option value="">-- Select --</option>
                                    <option value="1"
                                        {{ old('config.insurance', $preset?->insurance) == '1' ? 'selected' : '' }}>
                                        Enabled</option>
                                    <option value="0"
                                        {{ old('config.insurance', $preset?->insurance) == '0' ? 'selected' : '' }}>
                                        Disabled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="text-light small">Split Type</label>
                            <select name="config[split_type]"
                                class="form-select bg-black text-white border-secondary">
                                <option value="same_rank"
                                    {{ old('config.split_type', $preset?->split_type) == 'same_rank' ? 'selected' : '' }}>
                                    Same Rank</option>
                                <option value="same_value"
                                    {{ old('config.split_type', $preset?->split_type) == 'same_value' ? 'selected' : '' }}>
                                    Same Value</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="text-light small">Soft 17 Rule</label>
                            <select name="config[soft17rule]" class="form-select bg-black text-white border-secondary">
                                <option value="s17"
                                    {{ old('config.soft17rule', $preset?->soft17rule) == 's17' ? 'selected' : '' }}>Stand Soft 17
                                </option>
                                <option value="h17"
                                    {{ old('config.soft17rule', $preset?->soft17rule) == 'h17' ? 'selected' : '' }}>Hit Soft 17
                                </option>
                            </select>
                        </div>
                        {{-- <div class="col-md-3 mt-2">
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" name="config[enable_777_charlie]" value="1" id="enable777" {{ old('config.enable_777_charlie', $preset?->enable_777_charlie) ? 'checked' : '' }}>
            <label class="form-check-label text-light" for="enable777">777 Charlie Rule</label>
        </div>
    </div> --}}
                    @endif

                    {{-- ── MINI FLUSH ── --}}
                    @if ($code === 'MF')
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">H/L Min</label>
                                <input type="number" step="0.01" name="config[hl_min]" id="hlMin"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.hl_min', $preset?->hl_min) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">H/L Max</label>
                                <input type="number" step="0.01" name="config[hl_max]" id="hlMax"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.hl_max', $preset?->hl_max) }}">
                            </div>
                        </div>
                    @endif

                    {{-- ── CASINO WAR ── --}}
                    @if ($code === 'CW')
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Tie Min</label>
                                <input type="number" step="0.01" name="config[tie_min]" id="tieMin"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.tie_min', $preset?->tie_min) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Tie Max</label>
                                <input type="number" step="0.01" name="config[tie_max]" id="tieMax"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.tie_max', $preset?->tie_max) }}">
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
                    <h6 class="text-warning mb-3">④ Payout Rules</h6>
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
                                    @php
                                        $savedRule = $gameTable->payoutRules->firstWhere('payout_id', $rule->payout_id);
                                    @endphp
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
                                                    {{ $rule->is_active ? 'checked' : '' }}>
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
                                                    value="{{ $savedRule?->seed_value ?? '' }}"
                                                    placeholder="Enter seed" {{ $rule->is_active ? '' : 'disabled' }}>
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

            {{-- Submit --}}
            <div class="text-center mt-2 mb-5">
                <button type="submit" class="btn btn-warning px-5">
                    <i class="bi bi-save me-1"></i>Save Changes
                </button>
                <a href="{{ route('game_tables.index') }}" class="btn btn-outline-secondary px-4 ms-2">
                    Cancel
                </a>
            </div>

        </form>
    </div>

    <script>
        // ── Chip preset preview ───────────────────────────────────────────────
        document.getElementById('chipPresetSelect').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const chips = JSON.parse(opt.dataset.chips || '[]');
            const base = opt.dataset.base || '—';

            document.querySelectorAll('.chip-preview-val').forEach((el, i) => {
                el.textContent = chips[i] !== undefined ? chips[i] : '—';
            });
            document.getElementById('basePreview').textContent = base;
        });

        // ── Felt color label ──────────────────────────────────────────────────
        document.getElementById('feltColor').addEventListener('input', function() {
            document.getElementById('feltColorLabel').textContent = this.value;
        });
    </script>
</x-app-layout>

