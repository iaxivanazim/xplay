<x-app-layout>
<div class="content-wrapper p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="text-warning mb-0">
                <i class="bi bi-pencil-square me-2"></i>Edit Tab — <span class="text-white">{{ $tab->table_name }}</span>
            </h5>
            <div class="mt-1" style="font-size:12px; color:#666;">
                ID: {{ $tab->id }} &nbsp;|&nbsp;
                Game: <span class="text-warning">{{ $tab->gameType?->name }}</span> &nbsp;|&nbsp;
                Status:
                @php $ts = $tab->tabStatus; @endphp
                @if($tab->hasActiveSession())
                    <span style="color:#6fcf97;">IN SESSION (OTP: {{ $ts?->current_otp }})</span>
                @elseif(!$tab->isEnabled())
                    <span style="color:#eb5757;">DISABLED</span>
                @elseif($tab->isLocked())
                    <span style="color:#ff9800;">LOCKED</span>
                @else
                    <span style="color:#aaa;">IDLE</span>
                @endif
            </div>
        </div>
        <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary btn-sm">← Back to Tabs</a>
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
        <div class="alert alert-danger border-0 mb-4" style="background:#2e0d0d; color:#eb5757; font-size:13px;">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
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

            <form method="POST" action="{{ route('tabs.update', $tab->id) }}">
                @csrf @method('PUT')

                {{-- ① Basic config --}}
                <div class="card border-0 mb-4" style="background:#111;">
                    <div class="card-body">
                        <h6 class="text-warning mb-3" style="letter-spacing:0.06em;">① TAB CONFIGURATION</h6>
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="text-light small mb-1">Tab Name <span class="text-danger">*</span></label>
                                <input type="text" name="table_name"
                                    class="form-control bg-black text-white border-secondary"
                                    value="{{ old('table_name', $tab->table_name) }}"
                                    {{ $tab->hasActiveSession() ? 'disabled' : '' }} required>
                            </div>

                            <div class="col-md-3">
                                <label class="text-light small mb-1">Denomination</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-black border-secondary text-warning">$</span>
                                    <input type="number" name="denomination" step="0.0001" min="0.0001"
                                        class="form-control bg-black text-white border-secondary"
                                        value="{{ old('denomination', $tab->denomination) }}"
                                        {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="text-light small mb-1">Bet Index</label>
                                <select name="bet_index" class="form-select bg-black text-white border-secondary">
                                    @for($i = 1; $i <= 9; $i++)
                                        <option value="{{ $i }}" {{ $tab->bet_index == $i ? 'selected' : '' }}>Level {{ $i }}</option>
                                    @endfor
                                </select>
                            </div>

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

                {{-- ② Payout Rule Overrides --}}
                <div class="card border-0 mb-4" style="background:#111;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-warning mb-0" style="letter-spacing:0.06em;">② PAYOUT RULES</h6>
                            <span class="badge rounded-pill" style="background:#1a1a1a; color:#ffc107; border:1px solid #ffc10744; font-size:10px;">
                                {{ $payoutRules->where('is_active', 1)->count() }}/{{ $payoutRules->count() }} active
                            </span>
                        </div>

                        @if($payoutRules->isEmpty())
                            <div class="text-muted small fst-italic">No payout rules for this game type.</div>
                        @else
                            <div class="row g-2">
                                @foreach($payoutRules as $rule)
                                    <div class="col-sm-6 col-md-4">
                                        <label class="d-flex align-items-center gap-2 px-3 py-2 rounded cursor-pointer"
                                            style="background:#0d1117; border:1px solid {{ $rule->is_active ? '#2a5a2a' : '#222' }}; cursor:pointer; transition: border-color 0.2s;">
                                            <input type="checkbox"
                                                name="payout_overrides[{{ $rule->payout_id }}]"
                                                value="1"
                                                class="form-check-input border-secondary payout-toggle"
                                                style="background:#111;"
                                                {{ $rule->is_active ? 'checked' : '' }}>
                                            <div class="flex-grow-1">
                                                <div style="font-size:12px; color:#ddd;">{{ $rule->bet_name }}</div>
                                                <div style="font-size:10px; color:#555;">{{ $rule->bet_position }}</div>
                                            </div>
                                            <span class="fw-bold" style="font-size:13px; color:#ffc107;">
                                                {{ $rule->payout_multiplier }}x
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-warning fw-bold px-4"
                        {{ $tab->hasActiveSession() ? 'disabled' : '' }}>
                        <i class="bi bi-check-lg me-2"></i>Save Changes
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

<style>
.payout-toggle:checked { background-color: #ffc107; border-color: #ffc107; }
</style>
