<x-app-layout>
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="text-warning mb-1">
                    <i class="bi bi-pencil-square me-2"></i>Edit Tab: {{ $tab->table_name }} (Tab #{{ $tab->tab_number }})
                </h5>
                <p class="text-secondary small mb-0">Update network settings, hardware binding, and assigned game configurations.</p>
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

        <form method="POST" action="{{ route('tabs.update', $tab->id) }}">
            @csrf
            @method('PUT')

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
                                        value="{{ old('tab_number', $tab->tab_number) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Tab Name <span class="text-danger">*</span></label>
                                    <input type="text" name="table_name" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('table_name', $tab->table_name) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">IP Address</label>
                                    <input type="text" name="ip_address" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('ip_address', $tab->ip_address) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Active MAC Address</label>
                                    <div class="input-group">
                                        <input type="text" name="active_mac" class="form-control bg-black text-white border-secondary"
                                            value="{{ old('active_mac', $tab->active_mac) }}">
                                        @if($tab->active_mac)
                                            <button type="submit" form="unregister_mac_form" class="btn btn-outline-danger" title="Unregister MAC">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Denomination <span class="text-danger">*</span></label>
                                    <input type="number" step="0.0001" name="denomination" class="form-control bg-black text-white border-secondary"
                                        value="{{ old('denomination', $tab->denomination) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">Lock Status</label>
                                    <select name="lock_status" class="form-select bg-black text-white border-secondary">
                                        <option value="unlocked" {{ old('lock_status', $tab->lock_status) == 'unlocked' ? 'selected' : '' }}>Unlocked</option>
                                        <option value="locked" {{ old('lock_status', $tab->lock_status) == 'locked' ? 'selected' : '' }}>Locked</option>
                                        <option value="break" {{ old('lock_status', $tab->lock_status) == 'break' ? 'selected' : '' }}>Break Mode</option>
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
                                    @foreach($gameConfigs as $cfg)
                                        <option value="{{ $cfg->id }}" {{ old('game_config_id', $tab->game_config_id) == $cfg->id ? 'selected' : '' }}>
                                            {{ $cfg->name }} ({{ $cfg->gameType?->name }}) - Bets: {{ $cfg->min_bet }} to {{ $cfg->max_bet }}
                                        </option>
                                    @endforeach
                                </select>
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
                                                    {{ in_array($cfg->id, old('game_configs', $assignedConfigIds)) ? 'checked' : '' }}>
                                                <label class="form-check-label text-white small" for="cfg_{{ $cfg->id }}">
                                                    <strong>{{ $cfg->name }}</strong>
                                                    <span class="text-secondary d-block" style="font-size:10px;">{{ $cfg->gameType?->name }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
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
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="status" value="1" id="tab_status" {{ old('status', $tab->status) ? 'checked' : '' }}>
                                <label class="form-check-label text-white" for="tab_status">
                                    Tab Enabled
                                </label>
                            </div>

                            @if($tab->statusRecord)
                                <div class="p-3 rounded mb-3" style="background:#161f28; border:1px solid #233547;">
                                    <div class="text-warning small fw-bold mb-2">Live Session & Status Snapshot</div>
                                    <div class="text-secondary small">Current Balance: <strong class="text-white">${{ number_format($tab->statusRecord->current_balance, 2) }}</strong></div>
                                    <div class="text-secondary small">Active OTP: <strong class="text-info">{{ $tab->statusRecord->current_otp ?? 'None' }}</strong></div>
                                    <div class="text-secondary small">Player: <strong class="text-white">{{ $tab->statusRecord->player_name ?? 'None' }}</strong></div>
                                    <div class="text-secondary small">Connection: <strong class="text-success">{{ $tab->statusRecord->connection_status }}</strong></div>
                                    <div class="text-secondary small">Last Sync: <span class="text-white-50">{{ $tab->statusRecord->last_synced_at?->diffForHumans() ?? 'Never' }}</span></div>
                                </div>
                            @endif

                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                                <i class="bi bi-check2-circle me-1"></i>Update Tab Configuration
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Hidden Form for Unregistering MAC --}}
        @if($tab->active_mac)
            <form id="unregister_mac_form" method="POST" action="{{ route('tabs.unregister_mac', $tab->id) }}" class="d-none">
                @csrf
            </form>
        @endif

    </div>
</x-app-layout>
