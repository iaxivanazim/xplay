<x-app-layout>
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <h5 class="text-warning mb-0">
                    <i class="bi bi-gear-wide-connected me-2"></i>Game Configurations
                </h5>
                <span class="badge rounded-pill bg-dark border border-secondary text-secondary">
                    Total: {{ $configs->total() }}
                </span>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                {{-- Search & Game Type Filter --}}
                <form method="GET" action="{{ route('game_configs.index') }}" class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-black border-secondary text-secondary">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control bg-black text-white border-secondary"
                            placeholder="Search config..." value="{{ request('search') }}">
                        @if(request('search'))
                            <a href="{{ route('game_configs.index') }}" class="btn btn-outline-secondary border-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>

                    <select name="game_type_id" class="form-select form-select-sm bg-black text-white border-secondary"
                        onchange="this.form.submit()" style="width: 160px;">
                        <option value="">All Game Types</option>
                        @foreach($gameTypes as $gt)
                            <option value="{{ $gt->id }}" {{ request('game_type_id') == $gt->id ? 'selected' : '' }}>
                                {{ $gt->name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <a href="{{ route('game_configs.create') }}" class="btn btn-warning btn-sm fw-bold">
                    <i class="bi bi-plus-lg me-1"></i>New Game Config
                </a>
            </div>
        </div>

        {{-- Alerts --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 mb-4"
                style="background:#0f2e1a; color:#6fcf97;">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 mb-4"
                style="background:#2e1010; color:#eb5757;">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Configs List --}}
        <div class="row g-4">
            @forelse($configs as $cfg)
                @php
                    $badgeColor = match ($cfg->gameType?->code ?? '') {
                        'BAC'        => '#c9a227',
                        'ROL', 'ROU' => '#e63946',
                        'AB'         => '#00a86b',
                        default      => '#ffc107',
                    };
                    $activeRulesCount = $cfg->payoutRules->where('is_active', 1)->count();
                    $totalRulesCount  = $cfg->payoutRules->count();
                @endphp
                <div class="col-12">
                    <div class="card border-0 overflow-hidden"
                        style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05); border-left: 4px solid {{ $badgeColor }} !important;">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                
                                {{-- Left info --}}
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fs-5 fw-bold text-white">{{ $cfg->name }}</span>
                                        <span class="badge rounded-pill px-2 py-1"
                                            style="background:{{ $badgeColor }}22; color:{{ $badgeColor }}; border:1px solid {{ $badgeColor }}; font-size:11px;">
                                            {{ $cfg->gameType?->name ?? 'Unknown' }}
                                        </span>
                                        @if($cfg->is_default)
                                            <span class="badge bg-warning text-dark fw-bold" style="font-size:10px;">
                                                <i class="bi bi-star-fill me-1"></i>DEFAULT
                                            </span>
                                        @endif
                                        @if($cfg->is_active)
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
                                    </div>

                                    <div class="d-flex flex-wrap gap-4 text-secondary small mt-3">
                                        <div>
                                            <span class="text-white-50"><i class="bi bi-arrows-expand me-1"></i>Bet Range:</span>
                                            <span class="text-warning fw-bold">{{ $cfg->min_bet }}</span>
                                            <span class="text-secondary">to</span>
                                            <span class="text-warning fw-bold">{{ $cfg->max_bet }}</span>
                                        </div>
                                        @if($cfg->side_min_bet || $cfg->side_max_bet)
                                            <div>
                                                <span class="text-white-50">Side Bet:</span>
                                                <span class="text-warning fw-bold">{{ $cfg->side_min_bet ?? 0 }} - {{ $cfg->side_max_bet ?? 0 }}</span>
                                            </div>
                                        @endif
                                        @if($cfg->roulette_type && in_array($cfg->gameType?->code, ['ROL', 'ROU']))
                                            <div>
                                                <span class="text-white-50">Wheel:</span>
                                                <span class="text-info text-capitalize">{{ $cfg->roulette_type }} (Single Zero)</span>
                                            </div>
                                        @endif
                                        @if($cfg->gameType?->code === 'BAC')
                                            <div>
                                                <span class="text-white-50">Commission:</span>
                                                <span class="text-info">{{ $cfg->baccarat_6_commission ? 'No-Commission (Punto Banco / Super 6)' : ($cfg->commission * 100) . '%' }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <span class="text-white-50"><i class="bi bi-tablet me-1"></i>Assigned Tabs:</span>
                                            <span class="badge bg-dark border border-secondary text-white">{{ $cfg->tabs_count ?? $cfg->tabs->count() }}</span>
                                        </div>
                                        <div>
                                            <span class="text-white-50"><i class="bi bi-calculator me-1"></i>Payout Rules:</span>
                                            <span class="badge bg-dark border border-warning text-warning">{{ $activeRulesCount }}/{{ $totalRulesCount }} Active</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Right Actions --}}
                                <div class="d-flex align-items-center gap-2">
                                    @if(!$cfg->is_default)
                                        <form method="POST" action="{{ route('game_configs.set_default', $cfg->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Set as default config for this game type">
                                                <i class="bi bi-star me-1"></i>Set Default
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('game_configs.toggle_active', $cfg->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $cfg->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                                            <i class="bi {{ $cfg->is_active ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i>
                                            {{ $cfg->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>

                                    <a href="{{ route('game_configs.edit', $cfg->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </a>

                                    <form method="POST" action="{{ route('game_configs.destroy', $cfg->id) }}"
                                        onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            {{ ($cfg->tabs_count ?? $cfg->tabs->count()) > 0 ? 'disabled' : '' }}>
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Payout Rules pills --}}
                            @if($cfg->payoutRules->isNotEmpty())
                                <div class="mt-3 pt-3 border-top border-dark d-flex flex-wrap gap-2">
                                    @foreach($cfg->payoutRules->where('is_active', 1) as $rule)
                                        <span class="badge" style="background:#161f28; border:1px solid #233547; color:#cbd5e1; font-weight:normal; font-size:11px;">
                                            {{ $rule->payoutRule?->bet_name }}:
                                            <strong class="text-warning">{{ $rule->payout_multiplier ?? $rule->payoutRule?->payout_multiplier }}x</strong>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <div style="font-size:48px; opacity:0.2;">⚙️</div>
                    <div class="text-muted mt-3">No game configurations found.</div>
                    <a href="{{ route('game_configs.create') }}" class="btn btn-warning btn-sm mt-3">
                        + Create First Game Config
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if ($configs->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $configs->links() }}
            </div>
        @endif

    </div>
</x-app-layout>
