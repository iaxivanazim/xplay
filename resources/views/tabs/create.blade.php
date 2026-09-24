<x-app-layout>
<div class="content-wrapper p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="text-warning mb-0"><i class="bi bi-plus-circle me-2"></i>Add New Tab</h5>
        <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary btn-sm">← Back to Tabs</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 mb-4" style="background:#2e0d0d; color:#eb5757; font-size:13px;">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tabs.store') }}">
        @csrf

        {{-- ① Tab Identity --}}
        <div class="card border-0 mb-4" style="background:#111;">
            <div class="card-body">
                <h6 class="text-warning mb-3" style="letter-spacing:0.06em;">① TAB IDENTITY</h6>
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="text-light small mb-1">Tab Name <span class="text-danger">*</span></label>
                        <input type="text" name="table_name"
                            class="form-control bg-black text-white border-secondary"
                            placeholder="e.g. Amatic-01"
                            value="{{ old('table_name') }}" required>
                        <div class="form-text text-muted" style="font-size:10px;">Unique identifier for this tab station.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="text-light small mb-1">Game Type <span class="text-danger">*</span></label>
                        <select name="game_type_id" class="form-select bg-black text-white border-secondary" required>
                            <option value="">— Select Game —</option>
                            @foreach($gameTypes as $gt)
                                <option value="{{ $gt->id }}" {{ old('game_type_id') == $gt->id ? 'selected' : '' }}>
                                    {{ $gt->name }} ({{ $gt->code }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted" style="font-size:10px;">All tabs of the same game type share default payout rules.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="text-light small mb-1">Denomination</label>
                        <div class="input-group">
                            <span class="input-group-text bg-black border-secondary text-warning">$</span>
                            <input type="number" name="denomination" step="0.0001" min="0.0001"
                                class="form-control bg-black text-white border-secondary"
                                placeholder="1.0000"
                                value="{{ old('denomination', '1.0000') }}">
                        </div>
                        <div class="form-text text-muted" style="font-size:10px;">Base credit value. e.g. 1.00 = $1 per credit, 0.01 = 1¢ per credit.</div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ② Bet Configuration --}}
        <div class="card border-0 mb-4" style="background:#111;">
            <div class="card-body">
                <h6 class="text-warning mb-3" style="letter-spacing:0.06em;">② BET CONFIGURATION</h6>
                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="text-light small mb-1">Bet Index</label>
                        <select name="bet_index" class="form-select bg-black text-white border-secondary">
                            @for($i = 1; $i <= 9; $i++)
                                <option value="{{ $i }}" {{ old('bet_index', 1) == $i ? 'selected' : '' }}>
                                    Level {{ $i }}
                                </option>
                            @endfor
                        </select>
                        <div class="form-text text-muted" style="font-size:10px;">
                            Selects which pipe-separated tier of min/max from the game preset is active.
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="p-3 rounded" style="background:#0d1117; border:1px solid #1e2a3a; font-size:12px; color:#888;">
                            <i class="bi bi-info-circle me-2 text-warning"></i>
                            <strong class="text-warning">Default payout rules</strong> for the selected game type will be automatically applied to this tab.
                            You can override individual rules after creation via the <strong class="text-white">Edit</strong> page.
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ③ MAC Address (optional at create time) --}}
        <div class="card border-0 mb-4" style="background:#111;">
            <div class="card-body">
                <h6 class="text-warning mb-3" style="letter-spacing:0.06em;">③ HARDWARE BINDING <span class="text-secondary fw-normal" style="font-size:11px;">(optional — can be done later)</span></h6>
                <div class="row g-3">

                    <div class="col-md-5">
                        <label class="text-light small mb-1">MAC Address</label>
                        <input type="text" name="active_mac"
                            class="form-control bg-black text-white border-secondary"
                            placeholder="AA:BB:CC:DD:EE:FF"
                            value="{{ old('active_mac') }}"
                            pattern="^([0-9A-Fa-f]{2}[:\-]){5}([0-9A-Fa-f]{2})$">
                        <div class="form-text text-muted" style="font-size:10px;">
                            MAC address of the physical terminal. Format: AA:BB:CC:DD:EE:FF.
                            Leave blank to register later via the Edit page.
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="p-3 rounded" style="background:#1a1200; border:1px solid #ffc10733; font-size:12px; color:#aaa;">
                            <i class="bi bi-shield-lock me-2 text-warning"></i>
                            <strong class="text-warning">MAC binding</strong> ties this tab to a specific hardware terminal.
                            Once bound, only that terminal can send game results and heartbeats for this tab.
                            Terminals can also self-register via the API on first connection.
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="d-flex gap-3">
            <button type="submit" class="btn btn-warning fw-bold px-4">
                <i class="bi bi-check-lg me-2"></i>Create Tab
            </button>
            <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
        </div>

    </form>
</div>
</x-app-layout>
