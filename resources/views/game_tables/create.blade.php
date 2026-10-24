<x-app-layout>
    <div class="content-wrapper p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="text-warning mb-0">Create Game Table</h5>
            <a href="{{ route('game_tables.index') }}" class="btn btn-outline-warning btn-sm">← Back</a>
        </div>

        <form method="POST" action="{{ route('game_tables.store') }}" id="masterForm">
            @csrf

            {{-- ═══════════════════════════════════════ --}}
            {{-- SECTION 1: GAME TABLE                   --}}
            {{-- ═══════════════════════════════════════ --}}
            <div class="card bg-black border-warning mb-4">
                <div class="card-body">
                    <h6 class="text-warning mb-3">① Table Details</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-light small">Table Name</label>
                            <input type="text" name="table_name" id="tableName"
                                class="form-control bg-black text-white border-secondary"
                                value="{{ old('table_name') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">Game Type</label>
                            <select name="game_type_id" id="gameTypeSelect"
                                class="form-select bg-black text-white border-secondary" required>
                                <option value="">-- Select Game --</option>
                                @foreach ($gameTypes as $type)
                                    <option value="{{ $type->id }}" data-code="{{ $type->code }}"
                                        {{ old('game_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">
                                Active MAC Address
                                <span class="ms-1" style="color:#555; font-size:10px; letter-spacing:0.04em;">
                                    AUTO-REGISTERED BY DEVICE
                                </span>
                            </label>
                            <input type="text" class="form-control bg-black border-secondary"
                                style="color:#555; cursor:not-allowed;" value="Not yet registered" disabled>
                        </div>
                        {{-- <div class="col-md-4">
                            <label class="text-light small">
                                Float Reference
                                <span class="ms-1" style="color:#555; font-size:10px;">SUGGESTED OPENING FLOAT</span>
                            </label>
                            <input type="number" step="0.01" name="float"
                                class="form-control bg-black text-white border-secondary" value="10000" hidden>
                        </div> --}}
                        <input type="number" step="0.01" name="float"
                                class="form-control bg-black text-white border-secondary" value="100000" hidden>
                        <div class="col-md-4">
                            <label class="text-light small">Felt Color</label>
                            <div class="d-flex gap-2 align-items-center mt-1">
                                <input type="color" name="felt_color" id="feltColor"
                                    class="form-control form-control-color border-secondary"
                                    value="{{ old('felt_color', '#006400') }}"
                                    style="width:50px; height:38px; background:transparent;">
                                <span id="feltColorLabel"
                                    class="text-light small">{{ old('felt_color', '#006400') }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="text-light small">Shoe Type</label>
                            <select name="shoe_type_id" id="shoeTypeSelect"
                                class="form-select bg-black text-white border-secondary">
                                <option value="">-- Select Shoe Type --</option>
                                @foreach ($shoeTypes as $shoe)
                                    <option value="{{ $shoe->id }}"
                                        {{ old('shoe_type_id') == $shoe->id ? 'selected' : '' }}>
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
                                <option value=""><a href="{{ route('chips.index') }}">-- Select Preset --</a>
                                </option>
                                @foreach ($chipPresets as $chip)
                                    <option value="{{ $chip->id }}"
                                        data-chips="{{ json_encode([$chip->chip_1_value, $chip->chip_2_value, $chip->chip_3_value, $chip->chip_4_value, $chip->chip_5_value]) }}"
                                        data-base="{{ $chip->base_value }}"
                                        {{ old('chip_preset_id') == $chip->id ? 'selected' : '' }}>
                                        {{ $chip->preset_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Live chip preview --}}
                        <div class="col-md-8">
                            <div class="row text-center align-items-center" id="chipPreview">
                                @php $colors = ['red','blue','green','purple','gold']; @endphp
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

                    {{-- Preset Name (common) --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="text-light small">Config Preset Name</label>
                            <input type="text" name="config[name]" id="configName"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. High Roller Baccarat" required>
                        </div>
                        <div class="col-md-3">
                            <label class="text-light small">
                                Min Bet
                                <span class="ms-1" style="color:#555; font-size:10px;">SEPARATE MULTIPLE WITH
                                    |</span>
                            </label>
                            <input type="text" name="config[min_bet]" id="minBet"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="e.g. 100|200|500" value="{{ old('config.min_bet') }}" required>
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
                                placeholder="e.g. 1000|2000|5000" value="{{ old('config.max_bet') }}" required>
                            <div id="maxBetPreview" class="mt-1 d-flex flex-wrap gap-1"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="text-light small" id="burnCardLabel">Burn Card every round</label>
                            <input type="number" name="config[burn_card]" id="burnCard"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="Number of cards to burn" min="0" max="10">
                        </div>

                    </div>

                    {{-- ── BACCARAT ── --}}
                    <div class="game-fields" id="fields-BAC" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Side Min Bet</label>
                                <input type="number" step="0.01" name="config[side_min_bet]" id="sideMinBet"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Side Max Bet</label>
                                <input type="number" step="0.01" name="config[side_max_bet]" id="sideMaxBet"
                                    class="form-control bg-black text-white border-secondary">
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
                                    <option value="1">Commission</option>
                                    <option value="0">Non-Commission</option>
                                </select>
                                <div class="mt-1 d-flex flex-column gap-1" style="font-size:10px; color:#ffc107;">
                                    <span><i class="bi bi-info-circle me-1"></i>Banker: <span
                                            id="bankerMultiplier">—</span></span>
                                    <span><i class="bi bi-info-circle me-1"></i>B6: <span
                                            id="b6Multiplier">—</span></span>
                                </div>
                            </div>
                            {{-- <div class="col-md-3 d-flex flex-column justify-content-center gap-2 mt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="config[enable_pairbets]" value="1" id="enablePairbets">
                            <label class="form-check-label text-light" for="enablePairbets">Pair Bets</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="config[enable_lucky6]" value="1" id="enableLucky6">
                            <label class="form-check-label text-light" for="enableLucky6">Lucky 6</label>
                        </div>
                    </div> --}}
                        </div>
                    </div>

                    {{-- ── ANDAR BAHAR ── --}}
                    {{-- <div class="game-fields" id="fields-AB" style="display:none;">
                <div class="row g-3">
                    <div class="col-md-6 d-flex flex-column justify-content-center gap-2 mt-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="config[enable_super_andar]" value="1" id="enableSuperAndar">
                            <label class="form-check-label text-light" for="enableSuperAndar">Super Andar</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="config[enable_super_bahar]" value="1" id="enableSuperBahar">
                            <label class="form-check-label text-light" for="enableSuperBahar">Super Bahar</label>
                        </div>
                    </div>
                </div>
            </div> --}}

                    {{-- ── DRAGON TIGER ── --}}
                    <div class="game-fields" id="fields-DT" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Tie Min</label>
                                <input type="number" step="0.01" name="config[tie_min]" id="tieMin"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Tie Max</label>
                                <input type="number" step="0.01" name="config[tie_max]" id="tieMax"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                        </div>
                    </div>

                    {{-- ── THREE CARD POKER ── --}}
                    <div class="game-fields" id="fields-3CP" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Side Min</label>
                                <input type="number" step="0.01" name="config[side_min]" id="sideMin"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Side Max</label>
                                <input type="number" step="0.01" name="config[side_max]" id="sideMax"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            {{-- <div class="col-md-3">
                        <label class="text-light small">Six Card Bonus</label>
                        <input type="number" step="0.01" name="config[six_card_bonus]" class="form-control bg-black text-white border-secondary">
                    </div> --}}
                        </div>
                    </div>

                    {{-- ── BLACKJACK ── --}}
                    <div class="game-fields" id="fields-BJ" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Pair Min</label>
                                <input type="number" step="0.01" name="config[pair_min]" id="pairMin"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Pair Max</label>
                                <input type="number" step="0.01" name="config[pair_max]" id="pairMax"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Surrender Option</label>
                                <select name="config[surrender]"
                                    class="form-select bg-black text-white border-secondary">
                                    <option value="0">No Surrender</option>
                                    <option value="1">Surrender on any card</option>
                                    <option value="2">Surrender on any card except Ace</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Insurance</label>
                                <select name="config[insurance]"
                                    class="form-select bg-black text-white border-secondary">
                                    <option value="1">Enabled</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                        <label class="text-light small">Split Type</label>
                        <select name="config[split_type]" class="form-select bg-black text-white border-secondary">
                            <option value="same_rank">Same Rank</option>
                            <option value="same_value">Same Value</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="text-light small">Soft 17 Rule</label>
                        <select name="config[soft17rule]" class="form-select bg-black text-white border-secondary">
                            <option value="s17">Stand Soft 17</option>
                            <option value="h17">Hit Soft 17</option>
                        </select>
                    </div>
                    {{--<div class="col-md-3 mt-2">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="config[enable_777_charlie]" value="1" id="enable777">
                            <label class="form-check-label text-light" for="enable777">777 Charlie Rule</label>
                        </div>
                    </div> --}}
                        </div>
                    </div>

                    {{-- ── MINI FLUSH ── --}}
                    <div class="game-fields" id="fields-MF" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">H/L Min</label>
                                <input type="number" step="0.01" name="config[hl_min]" id="hlMin"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">H/L Max</label>
                                <input type="number" step="0.01" name="config[hl_max]" id="hlMax"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                        </div>
                    </div>

                    {{-- ── CASINO WAR ── --}}
                    <div class="game-fields" id="fields-CW" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-light small">Tie Min</label>
                                <input type="number" step="0.01" name="config[tie_min]" id="tieMin"
                                    class="form-control bg-black text-white border-secondary">
                            </div>
                            <div class="col-md-3">
                                <label class="text-light small">Tie Max</label>
                                <input type="number" step="0.01" name="config[tie_max]" id="tieMax"
                                    class="form-control bg-black text-white border-secondary">
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
                                    <td colspan="4" class="text-muted">Select a game type to load payout rules</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SUBMIT --}}
            <div class="text-center mt-2 mb-5">
                <button type="submit" class="btn btn-warning px-5">Create Table</button>
                <a href="{{ route('game_tables.index') }}" class="btn btn-outline-secondary px-4 ms-2">Cancel</a>
            </div>

        </form>
    </div>

</x-app-layout>
