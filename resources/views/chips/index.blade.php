<x-app-layout>
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="text-warning mb-0">Chip Presets</h5>

            <div class="d-flex align-items-center gap-3">
                {{-- Status Filter --}}
                <div class="btn-group" role="group">
                    <a href="?status=1"
                        class="btn btn-sm {{ $status == 1 ? 'btn-warning' : 'btn-outline-warning' }}">
                        Active
                    </a>
                    <a href="?status=0"
                        class="btn btn-sm {{ $status == 0 ? 'btn-warning' : 'btn-outline-warning' }}">
                        Inactive
                    </a>
                </div>

                <button class="btn btn-warning" onclick="newPreset()">+ New Preset</button>
            </div>
        </div>

        {{-- New Preset Card (hidden by default) --}}
        <div id="newPresetCard" class="card bg-black border-warning mb-4" style="display:none !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-warning mb-0">New Preset</h6>
                </div>
                <form class="chipForm" data-id="">
                    @csrf
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="text-warning small mb-1">PRESET NAME</label>
                            <input type="text" name="preset_name" class="form-control bg-dark text-white border-warning" placeholder="e.g. VIP Table Presets" required>
                        </div>
                    </div>

                    <div class="row text-center align-items-center">
                        @php $colors = ['red','blue','green','purple','gold']; @endphp
                        @for($i = 1; $i <= 5; $i++)
                            <div class="col-md-2">
                            <div class="casino-chip chip-{{ $colors[$i-1] }}">
                                <input type="text" name="chip{{ $i }}" class="chip-input" value="0">
                            </div>
                            <p class="mt-2 text-light">Chip {{ $i }}</p>
                    </div>
                    @endfor

                    {{-- Vertical Divider --}}
                    <div class="col-auto px-0">
                        <div style="width:1px; height:100px; background: linear-gradient(to bottom, transparent, #ffc107, transparent); opacity:0.6;"></div>
                    </div>

                    {{-- Base Value --}}
                    <div class="col-md-1">
                        <div class="d-flex flex-column align-items-center justify-content-center" style="height:80px;">
                            <label class="text-warning small mb-1" style="letter-spacing:0.05em;">BASE VALUE</label>
                            <input type="text"
                                name="base_value"
                                class="chip-input text-center"
                                style="width:70px; border-bottom: 1px solid #ffc107; background:transparent; color:#fff;"
                                value="0">
                        </div>
                        <p class="mt-2 text-light">Base</p>
                    </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-warning">Save Preset</button>
            <button type="button" class="btn btn-secondary" onclick="cancelNew()">Cancel</button>
        </div>
        </form>
    </div>
    </div>

    {{-- All Presets --}}
    @forelse($chips as $chip)
    <div class="card bg-black border-warning mb-4 preset-card" id="preset-card-{{ $chip->id }}">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="text-warning mb-0">{{ $chip->preset_name }}</h6>
                <span class="text-muted small">#{{ $chip->id }}</span>
            </div>
            <form class="chipForm" data-id="{{ $chip->id }}">
                @csrf
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="text-warning small mb-1">PRESET NAME</label>
                        <input type="text" name="preset_name" class="form-control bg-dark text-white border-warning" value="{{ $chip->preset_name }}" required {{ $chip->status == 0 ? 'disabled' : '' }}>
                    </div>
                </div>

                <div class="row text-center align-items-center">
                    @for($i = 1; $i <= 5; $i++)
                        <div class="col-md-2">
                        <div class="casino-chip chip-{{ $colors[$i-1] }} {{ $chip->status == 0 ? 'opacity-50' : '' }}">
                            <input type="text"
                                name="chip{{ $i }}"
                                class="chip-input"
                                value="{{ $chip->{'chip_'.$i.'_value'} }}"
                                {{ $chip->status == 0 ? 'disabled' : '' }}>
                        </div>
                        <p class="mt-2 text-light">Chip {{ $i }}</p>
                </div>
                @endfor

                {{-- Vertical Divider --}}
                <div class="col-auto px-0">
                    <div style="width:1px; height:100px; background: linear-gradient(to bottom, transparent, #ffc107, transparent); opacity:0.6;"></div>
                </div>

                {{-- Base Value --}}
                <div class="col-md-1">
                    <div class="d-flex flex-column align-items-center justify-content-center" style="height:80px;">
                        <label class="text-warning small mb-1" style="letter-spacing:0.05em;">BASE VALUE</label>
                        <input type="text"
                            name="base_value"
                            class="chip-input text-center"
                            style="width:70px; border-bottom: 1px solid #ffc107; background:transparent; color:#fff;"
                            value="{{ $chip->base_value ?? 0 }}">
                    </div>
                    <p class="mt-2 text-light">Base</p>
                </div>

        </div>
        <div class="text-center mt-4">
            @if($chip->status == 1)
            <button type="submit" class="btn btn-warning">Save Preset</button>
            <button type="button"
                class="btn btn-danger"
                onclick="deletePreset({{ $chip->id }})">
                Delete
            </button>
            @else
            <button type="button"
                class="btn btn-success"
                onclick="restorePreset({{ $chip->id }})">
                Restore
            </button>
            @endif
        </div>
        </form>
    </div>
    </div>
    @empty
    <div class="text-center text-muted py-5">
        No presets found. Click <strong>+ New Preset</strong> to create one.
    </div>
    @endforelse

    {{-- Pagination --}}
    @if($chips->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $chips->links() }}
    </div>
    @endif

    </div>
</x-app-layout>