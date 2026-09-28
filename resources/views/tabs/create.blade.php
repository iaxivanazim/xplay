<x-app-layout>
    <div class="content-wrapper p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="text-warning mb-0"><i class="bi bi-plus-circle me-2"></i>Create Tab</h5>
            <a href="{{ route('tabs.index') }}" class="btn btn-outline-warning btn-sm">← Back to Tabs</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger border-0 mb-4" style="background:#2e0d0d; color:#eb5757; font-size:13px;">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tabs.store') }}" id="masterForm">
            @csrf

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
                                placeholder="e.g. Amatic-01"
                                value="{{ old('table_name') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">Game Type <span class="text-danger">*</span></label>
                            <select name="game_type_id" id="gameTypeSelect"
                                class="form-select bg-black text-white border-secondary" required>
                                <option value="">-- Select Game --</option>
                                @foreach ($gameTypes as $type)
                                    <option value="{{ $type->id }}" data-code="{{ $type->code }}"
                                        {{ old('game_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }} ({{ $type->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">Active MAC Address</label>
                            <input type="text" name="active_mac" id="activeMac"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. AA:BB:CC:DD:EE:FF"
                                pattern="^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$"
                                value="{{ old('active_mac') }}">
                            <div class="form-text text-muted" style="font-size:10px;">Optional — will auto-bind when station connects.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="text-light small">Denomination</label>
                            <div class="input-group">
                                <span class="input-group-text bg-black border-secondary text-warning">$</span>
                                <input type="number" step="0.0001" min="0.0001" name="denomination"
                                    class="form-control bg-black text-white border-secondary"
                                    placeholder="1.0000"
                                    value="{{ old('denomination', '1.0000') }}">
                            </div>
                            <div class="form-text text-muted" style="font-size:10px;">Base credit value (e.g. 1.0000 = $1/credit).</div>
                        </div>

                        <div class="col-md-4">
                            <label class="text-light small">Bet Index</label>
                            <select name="bet_index" class="form-select bg-black text-white border-secondary">
                                @for ($i = 1; $i <= 9; $i++)
                                    <option value="{{ $i }}" {{ old('bet_index', 1) == $i ? 'selected' : '' }}>
                                        Level {{ $i }}
                                    </option>
                                @endfor
                            </select>
                            <div class="form-text text-muted" style="font-size:10px;">Selects which tier of pipe-separated limits is active.</div>
                        </div>

                        <input type="hidden" name="float" value="0">
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
                                class="form-select bg-black text-white border-secondary" required>
                                <option value="">-- Select Preset --</option>
                                @foreach ($chipPresets as $chip)
                                    <option value="{{ $chip->id }}"
                                        data-chips="{{ json_encode([$chip->chip_1_value, $chip->chip_2_value, $chip->chip_3_value, $chip->chip_4_value, $chip->chip_5_value]) }}"
                                        data-base="{{ $chip->base_value }}"
                                        {{ old('chip_preset_id') == $chip->id ? 'selected' : '' }}>
                                        {{ $chip->preset_name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($chipPresets->isEmpty())
                                <div class="mt-1" style="font-size:11px; color:#ffc107;">
                                    <i class="bi bi-info-circle me-1"></i>No chip presets found. <a href="{{ route('chips.index') }}" class="text-warning text-decoration-underline" target="_blank">Create one in Chip Config</a>
                                </div>
                            @endif
                        </div>

                        {{-- Live chip preview --}}
                        <div class="col-md-8">
                            <div class="row text-center align-items-center" id="chipPreview">
                                @php $colors = ['red', 'blue', 'green', 'purple', 'gold']; @endphp
                                @for ($i = 0; $i < 5; $i++)
                                    <div class="col-auto">
                                        <div class="casino-chip chip-{{ $colors[$i] }}"
                                            style="width:60px; height:60px;">
                                            <span class="chip-preview-val text-white fw-bold"
                                                style="font-size:14px;">—</span>
                                        </div>
                                    </div>
                                @endfor
                                <div class="col-auto px-2">
                                    <div
                                        style="width:1px; height:60px; background: linear-gradient(to bottom, transparent, #ffc107, transparent);">
                                    </div>
                                </div>
                                <div class="col-auto text-center">
                                    <label class="text-warning small d-block"
                                        style="letter-spacing:0.05em;">BASE</label>
                                    <span id="basePreview" class="text-white fw-bold">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════ --}}
            {{-- SECTION 3: GAME CONFIG (DYNAMIC)        --}}
            {{-- ═══════════════════════════════════════ --}}
            <div class="card bg-black border-warning mb-4" id="gameConfigSection" style="display:none;">
                <div class="card-body">
                    <h6 class="text-warning mb-3">③ Game Configuration</h6>

                    {{-- Common fields --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="text-light small">Config Preset Name <span class="text-danger">*</span></label>
                            <input type="text" name="config[name]" id="configName"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. Standard BAC" value="{{ old('config.name') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="text-light small">
                                Min Bet <span class="text-danger">*</span>
                                <span class="ms-1" style="color:#555; font-size:10px;">SEPARATE WITH |</span>
                            </label>
                            <input type="text" name="config[min_bet]" id="minBet"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. 100|200|500" value="{{ old('config.min_bet') }}" required>
                            <div id="minBetPreview" class="mt-1 d-flex flex-wrap gap-1"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="text-light small">
                                Max Bet <span class="text-danger">*</span>
                                <span class="ms-1" style="color:#555; font-size:10px;">SEPARATE WITH |</span>
                            </label>
                            <input type="text" name="config[max_bet]" id="maxBet"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. 1000|2000|5000" value="{{ old('config.max_bet') }}" required>
                            <div id="maxBetPreview" class="mt-1 d-flex flex-wrap gap-1"></div>
                        </div>
                        <div class="col-md-3" id="burnCardCol">
                            <label class="text-light small" id="burnCardLabel">Burn Card every round</label>
                            <input type="number" name="config[burn_card]" id="burnCard"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="Number of cards" min="0" max="10"
                                value="{{ old('config.burn_card') }}">
                        </div>
                    </div>

                    {{-- ── BACCARAT ── --}}
                    <div class="game-fields" id="fields-BAC" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Side Min Bet</label>
                                <input type="number" step="0.01" name="config[side_min_bet]" id="sideMinBet"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.side_min_bet') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Side Max Bet</label>
                                <input type="number" step="0.01" name="config[side_max_bet]" id="sideMaxBet"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('config.side_max_bet') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small d-flex align-items-center gap-2">
                                    Commission
                                    <span id="b6CommissionBadge"
                                        style="font-size:9px; padding:2px 6px; border-radius:10px;
                                               background:#2e1010; color:#eb5757; border:1px solid #eb5757;">
                                        B6 Inactive
                                    </span>
                                </label>
                                <select name="config[baccarat_6_commission]" id="b6CommissionSelect"
                                    class="form-select bg-black border-secondary"
                                    style="color:#555; cursor:not-allowed;" disabled>
                                    <option value="1" {{ old('config.baccarat_6_commission', 1) == 1 ? 'selected' : '' }}>Commission</option>
                                    <option value="0" {{ old('config.baccarat_6_commission', 1) == 0 ? 'selected' : '' }}>Non-Commission</option>
                                </select>
                                <div class="mt-1 d-flex flex-column gap-1" style="font-size:10px; color:#ffc107;">
                                    <span><i class="bi bi-info-circle me-1"></i>Banker: <span id="bankerMultiplier">—</span></span>
                                    <span><i class="bi bi-info-circle me-1"></i>B6: <span id="b6Multiplier">—</span></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── ANDAR BAHAR ── --}}
                    <div class="game-fields" id="fields-AB" style="display:none;">
                        {{-- Common min/max and Reset Threshold fields above --}}
                    </div>

                    {{-- ── ROULETTE ── --}}
                    <div class="game-fields" id="fields-ROL" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Roulette Type</label>
                                <select name="config[roulette_type]" id="rouletteType"
                                    class="form-select bg-black text-white border-secondary">
                                    <option value="european" {{ old('config.roulette_type', 'european') == 'european' ? 'selected' : '' }}>
                                        European (Single Zero 0)
                                    </option>
                                    <option value="american" {{ old('config.roulette_type') == 'american' ? 'selected' : '' }}>
                                        American (Double Zero 0, 00)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Side Min Bet</label>
                                <input type="number" step="0.01" name="config[side_min_bet]" id="sideMinBetROL"
                                    class="form-control bg-black text-white border-secondary"
                                    placeholder="Optional" value="{{ old('config.side_min_bet') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Side Max Bet</label>
                                <input type="number" step="0.01" name="config[side_max_bet]" id="sideMaxBetROL"
                                    class="form-control bg-black text-white border-secondary"
                                    placeholder="Optional" value="{{ old('config.side_max_bet') }}">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ═══════════════════════════════════════ --}}
            {{-- SECTION 4: PAYOUT RULES (DYNAMIC)       --}}
            {{-- ═══════════════════════════════════════ --}}
            <div class="card bg-black border-warning mb-4" id="payoutSection" style="display:none;">
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
                            <tbody id="payoutRulesBody">
                                <tr>
                                    <td colspan="5" class="text-muted">Select a game type to load payout rules</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SUBMIT --}}
            <div class="text-center mt-2 mb-5">
                <button type="submit" class="btn btn-warning px-5 fw-bold">Create Tab</button>
                <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary px-4 ms-2">Cancel</a>
            </div>

        </form>
    </div>
</x-app-layout>
