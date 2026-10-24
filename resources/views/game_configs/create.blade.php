<x-app-layout>
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="text-warning mb-1">
                    <i class="bi bi-plus-circle me-2"></i>New Game Configuration
                </h5>
                <p class="text-secondary small mb-0">Configure default rules and betting parameters for a game type.</p>
            </div>
            <a href="{{ route('game_configs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to List
            </a>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
            <div class="alert alert-danger border-0 mb-4" style="background:#2e1010; color:#eb5757;">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('game_configs.store') }}">
            @csrf

            <div class="row g-4">
                {{-- Basic Settings Card --}}
                <div class="col-lg-8">
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold">
                            <i class="bi bi-sliders me-2"></i>Basic Parameters
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Configuration Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control bg-black text-white border-secondary"
                                        placeholder="e.g. Standard European Roulette" value="{{ old('name') }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Game Type <span class="text-danger">*</span></label>
                                    <select name="game_type_id" id="game_type_id" class="form-select bg-black text-white border-secondary" required>
                                        <option value="">Select Game Type</option>
                                        @foreach($gameTypes as $gt)
                                            <option value="{{ $gt->id }}" data-code="{{ $gt->code }}" {{ old('game_type_id') == $gt->id ? 'selected' : '' }}>
                                                {{ $gt->name }} ({{ $gt->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Min Bet (Pipe-separated or single) <span class="text-danger">*</span></label>
                                    <input type="text" name="min_bet" class="form-control bg-black text-white border-secondary"
                                        placeholder="e.g. 10|25|50" value="{{ old('min_bet', '10|25|50') }}" required>
                                    <div class="form-text text-secondary" style="font-size:11px;">E.g. 10 or 10|25|50 for tier limits</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Max Bet (Pipe-separated or single) <span class="text-danger">*</span></label>
                                    <input type="text" name="max_bet" class="form-control bg-black text-white border-secondary"
                                        placeholder="e.g. 500|1000|2000" value="{{ old('max_bet', '500|1000|2000') }}" required>
                                    <div class="form-text text-secondary" style="font-size:11px;">Must have same number of values as Min Bet</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Side Bet Min</label>
                                    <input type="number" step="0.01" name="side_min_bet" class="form-control bg-black text-white border-secondary"
                                        placeholder="10.00" value="{{ old('side_min_bet', '10.00') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Side Bet Max</label>
                                    <input type="number" step="0.01" name="side_max_bet" class="form-control bg-black text-white border-secondary"
                                        placeholder="1000.00" value="{{ old('side_max_bet', '1000.00') }}">
                                </div>

                                <div class="col-md-6 card-game-field d-none">
                                    <label class="form-label text-secondary small">Burn Cards Count</label>
                                    <input type="number" name="burn_card" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('burn_card', 1) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Game Type Specific Settings Card --}}
                    {{-- Roulette Specific --}}
                    <div id="roulette_settings" class="card border-0 mb-4 d-none" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-danger fw-bold">
                            <i class="bi bi-disc me-2"></i>Roulette Wheel Configuration
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Roulette Variant</label>
                                    <select name="roulette_type" class="form-select bg-black text-white border-secondary">
                                        <option value="european" {{ old('roulette_type') == 'european' ? 'selected' : '' }}>European (Single Zero - 37 Numbers)</option>
                                        <option value="french" {{ old('roulette_type') == 'french' ? 'selected' : '' }}>French (Single Zero with La Partage)</option>
                                        <option value="american" {{ old('roulette_type') == 'american' ? 'selected' : '' }}>American (Double Zero - 38 Numbers)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Baccarat Specific --}}
                    <div id="baccarat_settings" class="card border-0 mb-4 d-none" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold">
                            <i class="bi bi-suit-spade me-2"></i>Baccarat Parameters
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Commission Mode</label>
                                    <select name="baccarat_6_commission" class="form-select bg-black text-white border-secondary">
                                        <option value="0" {{ old('baccarat_6_commission') == '0' ? 'selected' : '' }}>Standard Commission (5% on Banker)</option>
                                        <option value="1" {{ old('baccarat_6_commission') == '1' ? 'selected' : '' }}>No Commission (Super 6 / Banker 6 pays 50%)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Banker Commission Rate</label>
                                    <input type="number" step="0.001" name="commission" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('commission', '0.05') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Tie Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="tie_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('tie_min', 10) }}">
                                        <input type="number" step="0.01" name="tie_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('tie_max', 500) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Pair Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="pair_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('pair_min', 10) }}">
                                        <input type="number" step="0.01" name="pair_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('pair_max', 500) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Super 6 Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="super6_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('super6_min', 10) }}">
                                        <input type="number" step="0.01" name="super6_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('super6_max', 500) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Andar Bahar Specific --}}
                    <div id="andar_bahar_settings" class="card border-0 mb-4 d-none" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-success fw-bold">
                            <i class="bi bi-suit-heart me-2"></i>Andar Bahar Parameters
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Andar Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="andar_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('andar_min', 10) }}">
                                        <input type="number" step="0.01" name="andar_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('andar_max', 2500) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Bahar Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="bahar_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('bahar_min', 10) }}">
                                        <input type="number" step="0.01" name="bahar_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('bahar_max', 2500) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Payout Rules Selection --}}
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center">
                            <span class="text-warning fw-bold"><i class="bi bi-check2-square me-2"></i>Payout Rules & Multipliers</span>
                            <span class="text-secondary small" id="payout_count_badge">Select Game Type to load rules</span>
                        </div>
                        <div class="card-body p-4" id="payout_rules_container">
                            <div class="text-secondary small fst-italic">Please choose a game type above to view and customize its payout rules.</div>
                        </div>
                    </div>
                </div>

                {{-- Status & Save Card --}}
                <div class="col-lg-4">
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold">
                            <i class="bi bi-gear me-2"></i>Configuration Options
                        </div>
                        <div class="card-body p-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" {{ old('is_default') ? 'checked' : '' }}>
                                <label class="form-check-label text-white" for="is_default">
                                    Set as Default for Game Type
                                </label>
                                <div class="form-text text-secondary" style="font-size:11px;">
                                    When checked, new tabs created for this game type will default to this configuration.
                                </div>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label text-white" for="is_active">
                                    Active Configuration
                                </label>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                                <i class="bi bi-check2-circle me-1"></i>Save Configuration
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const gameTypeSelect = document.getElementById('game_type_id');
            const rouletteCard = document.getElementById('roulette_settings');
            const baccaratCard = document.getElementById('baccarat_settings');
            const andarBaharCard = document.getElementById('andar_bahar_settings');
            const cardFields = document.querySelectorAll('.card-game-field');
            const payoutContainer = document.getElementById('payout_rules_container');
            const payoutBadge = document.getElementById('payout_count_badge');

            function handleGameTypeChange() {
                const selectedOpt = gameTypeSelect.options[gameTypeSelect.selectedIndex];
                const code = selectedOpt ? selectedOpt.getAttribute('data-code') : '';
                const gameTypeId = gameTypeSelect.value;

                // Toggle visibility
                rouletteCard.classList.add('d-none');
                baccaratCard.classList.add('d-none');
                andarBaharCard.classList.add('d-none');
                cardFields.forEach(el => el.classList.add('d-none'));

                if (code === 'ROL' || code === 'ROU') {
                    rouletteCard.classList.remove('d-none');
                } else if (code === 'BAC') {
                    baccaratCard.classList.remove('d-none');
                    cardFields.forEach(el => el.classList.remove('d-none'));
                } else if (code === 'AB') {
                    andarBaharCard.classList.remove('d-none');
                    cardFields.forEach(el => el.classList.remove('d-none'));
                }

                // Load payout rules via API
                if (gameTypeId) {
                    payoutContainer.innerHTML = '<div class="text-warning small"><i class="bi bi-arrow-repeat spin me-2"></i>Loading payout rules...</div>';
                    fetch('/api/v1/payout-rules/' + gameTypeId)
                        .then(res => res.json())
                        .then(rules => {
                            if (!rules || rules.length === 0) {
                                payoutContainer.innerHTML = '<div class="text-secondary small">No payout rules found for this game type.</div>';
                                payoutBadge.innerText = '0 Rules';
                                return;
                            }

                            payoutBadge.innerText = rules.length + ' Rules Available';
                            let html = '<div class="row g-2">';
                            rules.forEach(r => {
                                const mult = r.payout_multiplier !== null ? r.payout_multiplier : '';
                                html += `
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background:#161f28; border:1px solid #233547;">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="payout_rules[${r.payout_id}]" value="1" id="rule_${r.payout_id}" checked>
                                            <label class="form-check-label text-white small" for="rule_${r.payout_id}">
                                                ${r.bet_name} ${r.bet_position ? '(' + r.bet_position + ')' : ''}
                                            </label>
                                        </div>
                                        <div class="input-group input-group-sm" style="width: 90px;">
                                            <input type="number" step="0.001" name="payout_multipliers[${r.payout_id}]" class="form-control bg-black text-warning border-secondary text-end px-1" value="${mult}">
                                            <span class="input-group-text bg-dark text-secondary border-secondary px-1">x</span>
                                        </div>
                                    </div>
                                </div>`;
                            });
                            html += '</div>';
                            payoutContainer.innerHTML = html;
                        })
                        .catch(err => {
                            payoutContainer.innerHTML = '<div class="text-danger small">Error loading rules.</div>';
                        });
                } else {
                    payoutContainer.innerHTML = '<div class="text-secondary small fst-italic">Please choose a game type above to view and customize its payout rules.</div>';
                    payoutBadge.innerText = 'Select Game Type';
                }
            }

            gameTypeSelect.addEventListener('change', handleGameTypeChange);
            if (gameTypeSelect.value) {
                handleGameTypeChange();
            }
        });
    </script>
    @endpush
</x-app-layout>
