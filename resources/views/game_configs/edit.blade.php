<x-app-layout>
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="text-warning mb-1">
                    <i class="bi bi-pencil-square me-2"></i>Edit Game Configuration: {{ $gameConfig->name }}
                </h5>
                <p class="text-secondary small mb-0">Modify game parameters, limits, and payout rules for {{ $gameConfig->gameType?->name }}.</p>
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

        <form method="POST" action="{{ route('game_configs.update', $gameConfig->id) }}">
            @csrf
            @method('PUT')

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
                                        value="{{ old('name', $gameConfig->name) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Game Type</label>
                                    <input type="text" class="form-control bg-dark text-white-50 border-secondary"
                                        value="{{ $gameConfig->gameType?->name }} ({{ $gameConfig->gameType?->code }})" disabled>
                                    <input type="hidden" name="game_type_id" value="{{ $gameConfig->game_type_id }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Min Bet (Pipe-separated or single) <span class="text-danger">*</span></label>
                                    <input type="text" name="min_bet" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('min_bet', $gameConfig->min_bet) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Max Bet (Pipe-separated or single) <span class="text-danger">*</span></label>
                                    <input type="text" name="max_bet" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('max_bet', $gameConfig->max_bet) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Side Bet Min</label>
                                    <input type="number" step="0.01" name="side_min_bet" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('side_min_bet', $gameConfig->side_min_bet) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Side Bet Max</label>
                                    <input type="number" step="0.01" name="side_max_bet" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('side_max_bet', $gameConfig->side_max_bet) }}">
                                </div>

                                @if(in_array($gameConfig->gameType?->code, ['BAC', 'AB']))
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Burn Cards Count</label>
                                    <input type="number" name="burn_card" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('burn_card', $gameConfig->burn_card) }}">
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Roulette Specific --}}
                    @if(in_array($gameConfig->gameType?->code, ['ROL', 'ROU']))
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-danger fw-bold">
                            <i class="bi bi-disc me-2"></i>Roulette Wheel Configuration
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Roulette Variant</label>
                                    <select name="roulette_type" class="form-select bg-black text-white border-secondary">
                                        <option value="european" {{ old('roulette_type', $gameConfig->roulette_type) == 'european' ? 'selected' : '' }}>European (Single Zero - 37 Numbers)</option>
                                        <option value="french" {{ old('roulette_type', $gameConfig->roulette_type) == 'french' ? 'selected' : '' }}>French (Single Zero with La Partage)</option>
                                        <option value="american" {{ old('roulette_type', $gameConfig->roulette_type) == 'american' ? 'selected' : '' }}>American (Double Zero - 38 Numbers)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Baccarat Specific --}}
                    @if($gameConfig->gameType?->code === 'BAC')
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold">
                            <i class="bi bi-suit-spade me-2"></i>Baccarat Parameters
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Commission Mode</label>
                                    <select name="baccarat_6_commission" class="form-select bg-black text-white border-secondary">
                                        <option value="0" {{ old('baccarat_6_commission', $gameConfig->baccarat_6_commission ? '1' : '0') == '0' ? 'selected' : '' }}>Standard Commission (5% on Banker)</option>
                                        <option value="1" {{ old('baccarat_6_commission', $gameConfig->baccarat_6_commission ? '1' : '0') == '1' ? 'selected' : '' }}>No Commission (Super 6 / Banker 6 pays 50%)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Banker Commission Rate</label>
                                    <input type="number" step="0.001" name="commission" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('commission', $gameConfig->commission) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Tie Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="tie_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('tie_min', $gameConfig->tie_min) }}">
                                        <input type="number" step="0.01" name="tie_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('tie_max', $gameConfig->tie_max) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Pair Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="pair_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('pair_min', $gameConfig->pair_min) }}">
                                        <input type="number" step="0.01" name="pair_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('pair_max', $gameConfig->pair_max) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Super 6 Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="super6_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('super6_min', $gameConfig->super6_min) }}">
                                        <input type="number" step="0.01" name="super6_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('super6_max', $gameConfig->super6_max) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Andar Bahar Specific --}}
                    @if($gameConfig->gameType?->code === 'AB')
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-success fw-bold">
                            <i class="bi bi-suit-heart me-2"></i>Andar Bahar Parameters
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Andar Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="andar_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('andar_min', $gameConfig->andar_min) }}">
                                        <input type="number" step="0.01" name="andar_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('andar_max', $gameConfig->andar_max) }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Bahar Min - Max</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="bahar_min" class="form-control bg-black text-white border-secondary" placeholder="Min" value="{{ old('bahar_min', $gameConfig->bahar_min) }}">
                                        <input type="number" step="0.01" name="bahar_max" class="form-control bg-black text-white border-secondary" placeholder="Max" value="{{ old('bahar_max', $gameConfig->bahar_max) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Payout Rules Selection --}}
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center">
                            <span class="text-warning fw-bold"><i class="bi bi-check2-square me-2"></i>Payout Rules & Multipliers</span>
                            <span class="text-secondary small">{{ $payoutRules->count() }} Rules Configured</span>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-2">
                                @foreach($payoutRules as $r)
                                    @php
                                        $saved = $savedRules->get($r->payout_id);
                                        $isActive = $saved ? $saved->is_active : true;
                                        $multiplier = $saved && $saved->payout_multiplier !== null ? $saved->payout_multiplier : $r->payout_multiplier;
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background:#161f28; border:1px solid #233547;">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="payout_rules[{{ $r->payout_id }}]" value="1" id="rule_{{ $r->payout_id }}" {{ $isActive ? 'checked' : '' }}>
                                                <label class="form-check-label text-white small" for="rule_{{ $r->payout_id }}">
                                                    {{ $r->bet_name }} {{ $r->bet_position ? '(' . $r->bet_position . ')' : '' }}
                                                </label>
                                            </div>
                                            <div class="input-group input-group-sm" style="width: 90px;">
                                                <input type="number" step="0.001" name="payout_multipliers[{{ $r->payout_id }}]" class="form-control bg-black text-warning border-secondary text-end px-1" value="{{ $multiplier }}">
                                                <span class="input-group-text bg-dark text-secondary border-secondary px-1">x</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
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
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" {{ old('is_default', $gameConfig->is_default) ? 'checked' : '' }}>
                                <label class="form-check-label text-white" for="is_default">
                                    Set as Default for {{ $gameConfig->gameType?->name }}
                                </label>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $gameConfig->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label text-white" for="is_active">
                                    Active Configuration
                                </label>
                            </div>

                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                                <i class="bi bi-check2-circle me-1"></i>Update Configuration
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</x-app-layout>
