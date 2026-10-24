<x-app-layout>
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="text-warning mb-1">
                    <i class="bi bi-tablet me-2"></i>Configure New Tab
                </h5>
                <p class="text-secondary small mb-0">Register tab hardware, network coordinates, and assign game configurations.</p>
            </div>
            <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Tab List
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

        <form method="POST" action="{{ route('tabs.store') }}">
            @csrf

            <div class="row g-4">
                {{-- Hardware & Network Info --}}
                <div class="col-lg-8">
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold">
                            <i class="bi bi-cpu me-2"></i>Hardware & Terminal Details
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Tab Number <span class="text-danger">*</span></label>
                                    <input type="number" name="tab_number" class="form-control bg-black text-white border-secondary"
                                        placeholder="e.g. 1" value="{{ old('tab_number', $nextNumber) }}" required>
                                    <div class="form-text text-secondary" style="font-size:11px;">Machine number identifier displayed on Cage dashboard</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Tab Name <span class="text-danger">*</span></label>
                                    <input type="text" name="table_name" class="form-control bg-black text-white border-secondary"
                                        placeholder="e.g. Amatic-1 or Apex-13" value="{{ old('table_name', 'Amatic-' . $nextNumber) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">IP Address</label>
                                    <input type="text" name="ip_address" class="form-control bg-black text-white border-secondary"
                                        placeholder="e.g. 192.168.1.101" value="{{ old('ip_address') }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Active MAC Address</label>
                                    <input type="text" name="active_mac" class="form-control bg-black text-white border-secondary"
                                        placeholder="e.g. 00:E0:4C:68:00:01" value="{{ old('active_mac') }}">
                                    <div class="form-text text-secondary" style="font-size:11px;">Hardware bound MAC (auto-registered on tab boot if left blank)</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Denomination <span class="text-danger">*</span></label>
                                    <input type="number" step="0.0001" name="denomination" class="form-control bg-black text-white border-secondary"
                                        placeholder="0.0100" value="{{ old('denomination', '0.0100') }}" required>
                                    <div class="form-text text-secondary" style="font-size:11px;">Credit conversion denomination ratio (e.g. 0.01)</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Initial Lock Status</label>
                                    <select name="lock_status" class="form-select bg-black text-white border-secondary">
                                        <option value="unlocked" {{ old('lock_status') == 'unlocked' ? 'selected' : '' }}>Unlocked (Normal Operation)</option>
                                        <option value="locked" {{ old('lock_status') == 'locked' ? 'selected' : '' }}>Locked (Disabled for play)</option>
                                        <option value="break" {{ old('lock_status') == 'break' ? 'selected' : '' }}>Break Mode</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Game Configuration Assignment --}}
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold">
                            <i class="bi bi-controller me-2"></i>Game Configuration Assignment
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-4">
                                <label class="form-label text-secondary small">Primary Default Game Config <span class="text-danger">*</span></label>
                                <select name="game_config_id" class="form-select bg-black text-white border-secondary" required>
                                    <option value="">Select Primary Game Config</option>
                                    @foreach($gameConfigs as $cfg)
                                        <option value="{{ $cfg->id }}" {{ old('game_config_id') == $cfg->id ? 'selected' : '' }}>
                                            {{ $cfg->name }} ({{ $cfg->gameType?->name }}) - Bets: {{ $cfg->min_bet }} to {{ $cfg->max_bet }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text text-secondary" style="font-size:11px;">Foreign key reference to default Game Config for this tab.</div>
                            </div>

                            <div>
                                <label class="form-label text-secondary small mb-2">
                                    Additional Available Game Configs for this Tab (Multi-Game Tab)
                                </label>
                                <div class="row g-2">
                                    @foreach($gameConfigs as $cfg)
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-2 rounded" style="background:#161f28; border:1px solid #233547;">
                                                <input class="form-check-input me-2" type="checkbox" name="game_configs[]" value="{{ $cfg->id }}" id="cfg_{{ $cfg->id }}"
                                                    {{ in_array($cfg->id, old('game_configs', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label text-white small" for="cfg_{{ $cfg->id }}">
                                                    <strong>{{ $cfg->name }}</strong>
                                                    <span class="text-secondary d-block" style="font-size:10px;">{{ $cfg->gameType?->name }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="form-text text-secondary mt-2" style="font-size:11px;">One tab can have multiple Game Configs assigned. Players can switch games at the tab.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status & Save Card --}}
                <div class="col-lg-4">
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold">
                            <i class="bi bi-shield-check me-2"></i>Status & State Recovery
                        </div>
                        <div class="card-body p-4">
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" name="status" value="1" id="tab_status" checked>
                                <label class="form-check-label text-white" for="tab_status">
                                    Tab Enabled
                                </label>
                            </div>

                            <div class="alert alert-dark border-secondary small text-secondary mb-4">
                                <i class="bi bi-info-circle-fill text-info me-1"></i>
                                Upon creation, an initial state record in <code>tab_statuses</code> will be established for this Tab to support failure recovery and session restore.
                            </div>

                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                                <i class="bi bi-check2-circle me-1"></i>Save & Deploy Tab
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</x-app-layout>
