<x-app-layout>
    <div class="content-wrapper p-4">

        {{-- Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h5 class="text-warning mb-1">
                    <i class="bi bi-collection-fill me-2"></i>Bulk Add Tabs
                </h5>
                <p class="text-secondary small mb-0">
                    Provision multiple tablet terminals in one action with a basic game configuration. Each tab can later be customized individually.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('tabs.create') }}" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Single Tab Create
                </a>
                <a href="{{ route('tabs.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Tab List
                </a>
            </div>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
            <div class="alert alert-danger border-0 mb-4" style="background:#2e1010; color:#eb5757;">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Validation Errors</div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tabs.bulk_store') }}" id="bulkTabForm">
            @csrf

            <div class="row g-4">
                {{-- Left Column: Base Config & Batch Generator --}}
                <div class="col-lg-5">

                    {{-- 1. Base Game Configuration Card --}}
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-controller me-2"></i>1. Basic Game Configuration</span>
                            <span class="badge bg-secondary" style="font-size:10px;">Applied to all</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <label class="form-label text-secondary small mb-1">
                                    Base Game Config <span class="text-danger">*</span>
                                </label>
                                <select name="game_config_id" id="gameConfigSelect" class="form-select bg-black text-white border-secondary" required>
                                    <option value="">Select Base Game Config</option>
                                    @foreach($gameConfigs as $cfg)
                                        <option value="{{ $cfg->id }}"
                                            {{ old('game_config_id', $defaultConfig?->id) == $cfg->id ? 'selected' : '' }}
                                            data-gamename="{{ $cfg->name }}"
                                            data-gametype="{{ $cfg->gameType?->name }}">
                                            {{ $cfg->name }} ({{ $cfg->gameType?->name }}) — Bets: {{ $cfg->min_bet }} to {{ $cfg->max_bet }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text text-secondary" style="font-size:11px;">
                                    This basic config is assigned to every tab created in this batch. Additional configs can be assigned individually later.
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label text-secondary small mb-1">Denomination <span class="text-danger">*</span></label>
                                    <input type="number" step="0.0001" name="denomination" id="denominationInput"
                                        class="form-control bg-black text-white border-secondary"
                                        placeholder="0.0100" value="{{ old('denomination', '0.0100') }}" required>
                                    <div class="form-text text-secondary" style="font-size:10px;">Default ratio (e.g. 0.01)</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-secondary small mb-1">Initial Lock</label>
                                    <select name="lock_status" id="lockStatusSelect" class="form-select bg-black text-white border-secondary">
                                        <option value="unlocked" {{ old('lock_status') == 'unlocked' ? 'selected' : '' }}>Unlocked</option>
                                        <option value="locked" {{ old('lock_status') == 'locked' ? 'selected' : '' }}>Locked</option>
                                        <option value="break" {{ old('lock_status') == 'break' ? 'selected' : '' }}>Break Mode</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="status" value="1" id="bulkStatusSwitch" checked>
                                <label class="form-check-label text-secondary small" for="bulkStatusSwitch">
                                    Enable newly created tabs immediately
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Sequence & Naming Generator Card --}}
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-magic me-2"></i>2. Sequence Generator</span>
                            <span class="badge bg-secondary" style="font-size:10px;">Batch Pattern</span>
                        </div>
                        <div class="card-body p-3">
                            {{-- Prefix --}}
                            <div class="mb-3">
                                <label class="form-label text-secondary small mb-1">Tab Name Prefix</label>
                                <input type="text" name="naming_prefix" id="namingPrefixInput"
                                    class="form-control bg-black text-white border-secondary"
                                    placeholder="e.g. Amatic- or Apex-" value="{{ old('naming_prefix', 'Amatic-') }}">
                                <div class="d-flex gap-1 mt-1">
                                    <span class="text-secondary small" style="font-size:11px;">Quick select:</span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 prefix-quick-btn" style="font-size:11px;" data-prefix="Amatic-">Amatic-</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 prefix-quick-btn" style="font-size:11px;" data-prefix="Apex-">Apex-</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 prefix-quick-btn" style="font-size:11px;" data-prefix="VIP-">VIP-</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 prefix-quick-btn" style="font-size:11px;" data-prefix="Tab-">Tab-</button>
                                </div>
                            </div>

                            {{-- Numbers & Quantity --}}
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label text-secondary small mb-1">Starting Number <span class="text-danger">*</span></label>
                                    <input type="number" name="start_number" id="startNumberInput"
                                        class="form-control bg-black text-white border-secondary font-monospace"
                                        value="{{ old('start_number', $nextNumber) }}" min="1" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-secondary small mb-1">Quantity (Tabs) <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" id="quantityInput"
                                        class="form-control bg-black text-white border-secondary font-monospace"
                                        value="{{ old('quantity', 5) }}" min="1" max="100" required>
                                </div>
                            </div>
                            <div class="d-flex gap-1 mb-3">
                                <span class="text-secondary small" style="font-size:11px;">Quick count:</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 qty-quick-btn" style="font-size:11px;" data-qty="5">+5</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 qty-quick-btn" style="font-size:11px;" data-qty="10">+10</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 qty-quick-btn" style="font-size:11px;" data-qty="15">+15</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 qty-quick-btn" style="font-size:11px;" data-qty="20">+20</button>
                            </div>

                            {{-- Starting IP --}}
                            <div class="mb-3">
                                <label class="form-label text-secondary small mb-1">Starting IP Address (Optional)</label>
                                <input type="text" name="start_ip" id="startIpInput"
                                    class="form-control bg-black text-white border-secondary font-monospace"
                                    placeholder="e.g. 192.168.1.101" value="{{ old('start_ip') }}">
                                <div class="form-text text-secondary" style="font-size:11px;">
                                    Consecutive IPs will be assigned (e.g. .101, .102...). Leave blank if tabs obtain IPs via DHCP.
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-warning w-100 btn-sm" id="btnRegenerateTable">
                                <i class="bi bi-arrow-repeat me-1"></i>Regenerate Preview Table
                            </button>
                        </div>
                    </div>

                </div>

                {{-- Right Column: Interactive Live Preview & Customizer Table --}}
                <div class="col-lg-7">
                    <div class="card border-0 mb-4" style="background:#111; box-shadow: 0 0 25px rgba(255,215,0,0.05);">
                        <div class="card-header bg-transparent border-secondary text-warning fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span><i class="bi bi-table me-2"></i>3. Batch Preview & Customizer</span>
                                <span class="badge bg-warning text-dark font-monospace fw-bold" id="previewBadge">5 Tabs</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnAddRowBtn">
                                    <i class="bi bi-plus-lg me-1"></i>Add Row
                                </button>
                            </div>
                        </div>

                        <div class="card-body p-3">
                            <div class="table-responsive rounded border border-secondary mb-3" style="max-height: 480px; overflow-y: auto;">
                                <table class="table table-dark table-sm align-middle mb-0" id="previewTable" style="font-size:12px;">
                                    <thead class="table-secondary text-dark text-uppercase" style="font-size:11px; position: sticky; top: 0; z-index: 2;">
                                        <tr>
                                            <th style="width: 35px;" class="text-center">#</th>
                                            <th style="width: 90px;">Tab #</th>
                                            <th>Tab Name</th>
                                            <th style="width: 140px;">IP Address</th>
                                            <th style="width: 140px;">MAC Address</th>
                                            <th style="width: 40px;" class="text-center">Del</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody">
                                        {{-- Injected dynamically via JS --}}
                                    </tbody>
                                </table>
                            </div>

                            {{-- Notice about Individual Customization --}}
                            <div class="p-3 rounded mb-3" style="background:#0b1928; border:1px solid #1c3652;">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-info-circle-fill text-info fs-5 mt-1"></i>
                                    <div>
                                        <div class="text-white fw-bold small">Editable Individual Tabs After Bulk Creation</div>
                                        <div class="text-secondary" style="font-size:11.5px; line-height: 1.4;">
                                            Every tab created will immediately have its own database identity, active recovery snapshot, and default config.
                                            You can click <strong>Edit</strong> on any tab later from the <a href="{{ route('tabs.index') }}" class="text-warning text-decoration-none">Tabs List</a> or <a href="{{ route('dashboard') }}" class="text-warning text-decoration-none">Cage Dashboard</a> to assign additional game configurations, change betting limits, or bind hardware MAC addresses.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Submit Button --}}
                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm" id="submitBulkBtn">
                                <i class="bi bi-check-circle-fill me-2"></i>Create All <span id="submitCountLabel">5</span> Tabs Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Dynamic Preview Table Generation Script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const prefixInput     = document.getElementById('namingPrefixInput');
            const startNumInput   = document.getElementById('startNumberInput');
            const quantityInput   = document.getElementById('quantityInput');
            const startIpInput    = document.getElementById('startIpInput');
            const tbody           = document.getElementById('previewTableBody');
            const previewBadge    = document.getElementById('previewBadge');
            const submitCountLbl  = document.getElementById('submitCountLabel');
            const submitBtn       = document.getElementById('submitBulkBtn');

            // Quick prefix buttons
            document.querySelectorAll('.prefix-quick-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    prefixInput.value = this.dataset.prefix;
                    generatePreview();
                });
            });

            // Quick quantity buttons
            document.querySelectorAll('.qty-quick-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    quantityInput.value = parseInt(this.dataset.qty, 10);
                    generatePreview();
                });
            });

            // Regenerate button
            document.getElementById('btnRegenerateTable').addEventListener('click', generatePreview);

            // Auto-update on inputs change
            [prefixInput, startNumInput, quantityInput, startIpInput].forEach(elem => {
                elem.addEventListener('input', debounce(generatePreview, 250));
            });

            // Add single custom row
            document.getElementById('btnAddRowBtn').addEventListener('click', function () {
                const currentRows = tbody.querySelectorAll('tr').length;
                let nextNum = 1;
                const lastNumInput = tbody.querySelector(`tr:last-child input[name$="[tab_number]"]`);
                if (lastNumInput && !isNaN(parseInt(lastNumInput.value, 10))) {
                    nextNum = parseInt(lastNumInput.value, 10) + 1;
                } else if (!isNaN(parseInt(startNumInput.value, 10))) {
                    nextNum = parseInt(startNumInput.value, 10) + currentRows;
                }

                const prefix = prefixInput.value || 'Tab-';
                addRow(currentRows, nextNum, prefix + nextNum, '', '');
                updateCounters();
            });

            // IP increment utility
            function calculateIp(baseIp, offset) {
                if (!baseIp || !baseIp.includes('.')) return '';
                const parts = baseIp.trim().split('.');
                if (parts.length !== 4) return '';
                const lastOctet = parseInt(parts[3], 10);
                if (isNaN(lastOctet)) return '';
                const newOctet = lastOctet + offset;
                if (newOctet > 254) return '';
                return `${parts[0]}.${parts[1]}.${parts[2]}.${newOctet}`;
            }

            function addRow(index, tabNum, tabName, ip, mac) {
                const tr = document.createElement('tr');
                tr.dataset.rowIndex = index;
                tr.innerHTML = `
                    <td class="text-center text-secondary font-monospace">${index + 1}</td>
                    <td>
                        <input type="number" name="tabs[${index}][tab_number]" value="${tabNum}"
                            class="form-control form-control-sm bg-black text-warning border-secondary font-monospace text-center fw-bold row-tab-number" required>
                    </td>
                    <td>
                        <input type="text" name="tabs[${index}][table_name]" value="${tabName}"
                            class="form-control form-control-sm bg-black text-white border-secondary font-monospace fw-bold row-tab-name" required>
                    </td>
                    <td>
                        <input type="text" name="tabs[${index}][ip_address]" value="${ip || ''}"
                            class="form-control form-control-sm bg-black text-white border-secondary font-monospace" placeholder="Optional / DHCP">
                    </td>
                    <td>
                        <input type="text" name="tabs[${index}][active_mac]" value="${mac || ''}"
                            class="form-control form-control-sm bg-black text-white border-secondary font-monospace" placeholder="Optional">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-danger btn-sm p-1 py-0 delete-row-btn" title="Remove Tab">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;

                tr.querySelector('.delete-row-btn').addEventListener('click', function () {
                    tr.remove();
                    reindexRows();
                    updateCounters();
                });

                tbody.appendChild(tr);
            }

            function reindexRows() {
                tbody.querySelectorAll('tr').forEach((tr, idx) => {
                    tr.dataset.rowIndex = idx;
                    tr.querySelector('td:first-child').textContent = idx + 1;
                    tr.querySelectorAll('input').forEach(input => {
                        const name = input.getAttribute('name');
                        if (name) {
                            input.setAttribute('name', name.replace(/tabs\[\d+\]/, `tabs[${idx}]`));
                        }
                    });
                });
            }

            function updateCounters() {
                const total = tbody.querySelectorAll('tr').length;
                previewBadge.textContent = `${total} Tab${total === 1 ? '' : 's'}`;
                submitCountLbl.textContent = total;
                submitBtn.disabled = (total === 0);
            }

            function generatePreview() {
                const prefix    = prefixInput.value || 'Tab-';
                let startNum    = parseInt(startNumInput.value, 10);
                let count       = parseInt(quantityInput.value, 10);
                const startIp   = startIpInput.value;

                if (isNaN(startNum) || startNum < 1) startNum = 1;
                if (isNaN(count) || count < 1) count = 1;
                if (count > 100) count = 100;

                tbody.innerHTML = '';

                for (let i = 0; i < count; i++) {
                    const num = startNum + i;
                    const name = prefix + num;
                    const ip = calculateIp(startIp, i);
                    addRow(i, num, name, ip, '');
                }

                updateCounters();
            }

            function debounce(func, wait) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

            // Initial render
            generatePreview();
        });
    </script>
</x-app-layout>
