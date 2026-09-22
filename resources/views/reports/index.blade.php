<x-app-layout>
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h5 class="text-warning mb-0">Dynamic Reports</h5>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-printer me-1"></i>Print PDF
                </button>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-warning btn-sm">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export Excel (CSV)
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card bg-black border-warning mb-4 no-print">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end"
                    x-data="{
                        reportType: '{{ $reportType }}',
                        tableId: '{{ $tableId }}',
                        txnType: '{{ $txnType ?? '' }}'
                    }">
                    <div class="col-md-3">
                        <label class="text-light small">Report Type</label>
                        <select name="type" class="form-select bg-black text-white border-secondary"
                            x-model="reportType">
                            <option value="gameday" {{ $reportType == 'gameday' ? 'selected' : '' }}>Gaming Day Report</option>
                            <option value="ledger"  {{ $reportType == 'ledger'  ? 'selected' : '' }}>Ledger Report</option>
                            <option value="float"   {{ $reportType == 'float'   ? 'selected' : '' }}>Table Float Report</option>
                            <option value="table"   {{ $reportType == 'table'   ? 'selected' : '' }}>Table Info Report</option>
                        </select>
                    </div>

                    {{-- Gameday Report: single date picker --}}
                    <div class="col-md-2"
                        x-show="reportType === 'gameday'"
                        style="{{ $reportType === 'gameday' ? '' : 'display:none;' }}">
                        <label class="text-light small">Game Day</label>
                        <input type="date" name="gameday"
                            class="form-control bg-black text-white border-secondary"
                            value="{{ $gameday }}">
                    </div>

                    {{-- Other reports: from/to date range --}}
                    <div class="col-md-2"
                        x-show="reportType !== 'gameday' && reportType !== 'table'"
                        style="{{ ($reportType !== 'gameday' && $reportType !== 'table') ? '' : 'display:none;' }}">
                        <label class="text-light small">From Date</label>
                        <input type="date" name="from_date"
                            class="form-control bg-black text-white border-secondary"
                            value="{{ $fromDate }}">
                    </div>
                    <div class="col-md-2"
                        x-show="reportType !== 'gameday' && reportType !== 'table'"
                        style="{{ ($reportType !== 'gameday' && $reportType !== 'table') ? '' : 'display:none;' }}">
                        <label class="text-light small">To Date</label>
                        <input type="date" name="to_date"
                            class="form-control bg-black text-white border-secondary"
                            value="{{ $toDate }}">
                    </div>

                    {{-- Table filter: ledger / float only --}}
                    <div class="col-md-3"
                        x-show="reportType === 'ledger' || reportType === 'float'"
                        style="{{ ($reportType === 'ledger' || $reportType === 'float') ? '' : 'display:none;' }}">
                        <label class="text-light small">Table <span class="text-secondary">(Optional)</span></label>
                        <select name="table_id" class="form-select bg-black text-white border-secondary"
                            x-model="tableId">
                            <option value="">-- All Tables --</option>
                            @foreach($tables as $table)
                                <option value="{{ $table->id }}" {{ $tableId == $table->id ? 'selected' : '' }}>
                                    {{ $table->table_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tab ID filter: only for Ledger + specific table selected --}}
                    <div class="col-md-2"
                        x-show="reportType === 'ledger' && tableId !== ''"
                        style="{{ ($reportType === 'ledger' && $tableId) ? '' : 'display:none;' }}">
                        <label class="text-light small">Tab ID <span class="text-secondary">(Optional)</span></label>
                        @if(empty($tabIds))
                            <select name="tab_id" class="form-select bg-black text-secondary border-secondary" disabled>
                                <option>— Select Table —</option>
                            </select>
                        @else
                            <select name="tab_id" class="form-select bg-black text-white border-secondary">
                                <option value="">All Tabs</option>
                                @foreach($tabIds as $tid)
                                    <option value="{{ $tid }}" {{ ($tabId ?? '') == $tid ? 'selected' : '' }}>
                                        {{ $tid }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Txn Type filter: only for Ledger --}}
                    <div class="col-md-2"
                        x-show="reportType === 'ledger'"
                        style="{{ $reportType === 'ledger' ? '' : 'display:none;' }}">
                        <label class="text-light small">Txn Type <span class="text-secondary">(Optional)</span></label>
                        <select name="txn_type" class="form-select bg-black text-white border-secondary"
                            x-model="txnType">
                            <option value="">All Types</option>
                            @foreach(['FILL','CREDIT','DROP','ADJUST','CASHOUT','BUYIN','PAYOUT','VOID','BET'] as $t)
                                <option value="{{ $t }}" {{ ($txnType ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Payment Medium filter: only for BUYIN / DROP --}}
                    <div class="col-md-2"
                        x-show="reportType === 'ledger' && (txnType === 'BUYIN' || txnType === 'DROP')"
                        style="{{ ($reportType === 'ledger' && in_array($txnType ?? '', ['BUYIN','DROP'])) ? '' : 'display:none;' }}">
                        <label class="text-light small">Medium <span class="text-secondary">(Optional)</span></label>
                        <select name="payment_medium" class="form-select bg-black text-white border-secondary">
                            <option value="">All</option>
                            <option value="CASH"  {{ ($paymentMedium ?? '') == 'CASH'  ? 'selected' : '' }}>CASH</option>
                            <option value="CHIPS" {{ ($paymentMedium ?? '') == 'CHIPS' ? 'selected' : '' }}
                                x-show="txnType === 'BUYIN'"
                                style="{{ ($txnType ?? '') === 'BUYIN' ? '' : 'display:none;' }}">CHIPS</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-warning w-100">Generate</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Report Content --}}
        <div class="card bg-black border-secondary report-container">
            <div class="card-body">

                {{-- Print header --}}
                <div class="text-center mb-4 only-print">
                    @if($reportType === 'gameday')
                        <h4 class="text-warning">Gaming Day Report</h4>
                        <p class="text-light small">Date: {{ $gameday }}</p>
                    @else
                        <h4 class="text-warning">Report: {{ ucfirst($reportType) }}</h4>
                        <p class="text-light small">
                            Period: {{ $fromDate }} to {{ $toDate }}
                            @if($tableId) | Table: {{ $tables->find($tableId)->table_name ?? '' }} @endif
                            @if(request('tab_id')) | Tab ID: {{ request('tab_id') }} @endif
                        </p>
                    @endif
                </div>

                {{-- ══════════════════════════════════════════
                     GAMEDAY REPORT — Blocked by table
                     ══════════════════════════════════════════ --}}
                @if($reportType === 'gameday')
                    @if($data->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                            No activity found for <strong>{{ $gameday }}</strong>.
                        </div>
                    @else
                        @foreach($data as $block)
                        <div class="mb-4 table-block">
                            {{-- Table Block Header --}}
                            <div class="d-flex justify-content-between align-items-center
                                        p-2 px-3 mb-0 rounded-top border border-warning border-bottom-0"
                                 style="background: #1a1300;">
                                <div>
                                    <span class="fw-bold text-warning me-2">{{ $block->table_name }}</span>
                                    <span class="badge bg-secondary text-white me-1">{{ $block->game_type }}</span>
                                    <span class="badge {{ $block->float_status === 'Closed' ? 'bg-success' : ($block->float_status === 'Open' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        {{ $block->float_status }}
                                    </span>
                                </div>
                                <div class="d-flex gap-3 small text-light flex-wrap">
                                    <span>
                                        <span class="text-secondary">Total Opening Float:</span>
                                        <span class="fw-semibold text-info">
                                            {{ $block->opening_float !== null ? number_format($block->opening_float, 2) : '—' }}
                                        </span>
                                    </span>
                                    <span>
                                        {{-- Closing = Opening + Fills - Credits + BuyIn(cash+chips) - Cashout --}}
                                        <span class="text-secondary">Total Closing Float:</span>
                                        <span class="fw-semibold text-warning">
                                            {{ $block->closing_float !== null ? number_format($block->closing_float, 2) : '—' }}
                                        </span>
                                    </span>
                                    <span>
                                        {{-- Result = Closing Float - Opening Float --}}
                                        <span class="text-secondary">Result:</span>
                                        @if($block->result !== null)
                                            @php $resultPositive = $block->result >= 0; @endphp
                                            <span class="fw-semibold {{ $resultPositive ? 'text-success' : 'text-danger' }}">
                                                {{ ($resultPositive ? '+' : '') . number_format($block->result, 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                            </div>

                            {{-- Tab Rows Table --}}
                            <div class="table-responsive border border-warning rounded-bottom">
                                <table class="table table-dark table-hover align-middle mb-0 border-secondary">
                                    <thead style="background:#1a1a00;">
                                        <tr class="text-warning small">
                                            <th class="ps-3">#</th>
                                            <th>Players</th>
                                            <th class="text-end">Total Fills</th>
                                            <th class="text-end">Total Credits</th>
                                            <th class="text-end">Total Buy-In</th>
                                            <th class="text-end pe-3">Total Cash-Out</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($block->tabs as $i => $tab)
                                        <tr>
                                            <td class="ps-3 small">{{ $i + 1 }}</td>
                                            <td>
                                                <span class="badge rounded-pill"
                                                    style="background:#1a1a2e; color:#a78bfa; border:1px solid #a78bfa55; font-size:11px; font-family:monospace; padding:4px 10px;">
                                                    {{ $tab->tab_id == 0 ? 'Dealer' : 'Player ' . $tab->tab_id }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                @if($tab->total_fills != 0)
                                                    <span class="text-success fw-semibold">{{ number_format($tab->total_fills, 2) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if($tab->total_credits != 0)
                                                    <span class="text-danger fw-semibold">{{ number_format($tab->total_credits, 2) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if($tab->total_buy != 0)
                                                    <span class="text-info fw-semibold">{{ number_format($tab->total_buy, 2) }}</span>
                                                    <div class="text-muted" style="font-size:10px; line-height:1.2;">
                                                        @if($tab->total_buy_cash != 0)
                                                            <span>Cash: {{ number_format($tab->total_buy_cash, 2) }}</span>
                                                        @endif
                                                        @if($tab->total_buy_chips != 0)
                                                            <span class="{{ $tab->total_buy_cash != 0 ? 'ms-1' : '' }}">Chips: {{ number_format($tab->total_buy_chips, 2) }}</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-3">
                                                @if($tab->total_cashout != 0)
                                                    <span class="fw-semibold" style="color:#f59e0b;">{{ number_format($tab->total_cashout, 2) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-3 text-muted small fst-italic">
                                                Float present but no tab activity recorded.
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    @if($block->tabs->count() > 1)
                                    <tfoot style="background:#0d0d0d; border-top:1px solid #ffc107;">
                                        <tr class="text-warning small fw-bold">
                                            <td colspan="2" class="ps-3">Totals</td>
                                            <td class="text-end text-success">{{ number_format($block->tabs->sum('total_fills'), 2) }}</td>
                                            <td class="text-end text-danger">{{ number_format($block->tabs->sum('total_credits'), 2) }}</td>
                                            <td class="text-end text-info">{{ number_format($block->tabs->sum('total_buy'), 2) }}</td>
                                            <td class="text-end pe-3" style="color:#f59e0b;">{{ number_format($block->tabs->sum('total_cashout'), 2) }}</td>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                        @endforeach
                    @endif

                {{-- ══════════════════════════════════════════
                     ALL OTHER REPORTS — flat table
                     ══════════════════════════════════════════ --}}
                @else
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border-secondary">
                            <thead class="text-warning border-secondary">
                                <tr>
                                    @if($reportType == 'ledger')
                                        <th>ID</th>
                                        <th>Table</th>
                                        <th>Tab ID</th>
                                        <th>Type</th>
                                        <th>Medium</th>
                                        <th>Amount</th>
                                        <th>Float Balance</th>
                                        <th>Gameday</th>
                                        <th>Reference</th>
                                        <th>At</th>
                                    @elseif($reportType == 'float')
                                        <th>ID</th>
                                        <th>Table</th>
                                        <th>Gameday</th>
                                        <th>Status</th>
                                        <th class="text-end">Opening Float</th>
                                        <th class="text-end">Closing Float</th>
                                        <th class="text-end">Result</th>
                                        <th>Opened At</th>
                                        <th>Closed At</th>
                                    @elseif($reportType == 'table')
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Game Type</th>
                                        <th>MAC Address</th>
                                        <th>Status</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data as $row)
                                    <tr>
                                        @if($reportType == 'ledger')
                                            <td>{{ $row->txn_id }}</td>
                                            <td>{{ $row->gameTable->table_name ?? 'N/A' }}</td>
                                            <td>
                                                @if($row->tab_id)
                                                    <span class="badge rounded-pill"
                                                        style="background:#1a1a2e; color:#a78bfa; border:1px solid #a78bfa55; font-size:10px; font-family:monospace;">
                                                        {{ $row->tab_id }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $badgeClass = match($row->txn_type) {
                                                        'FILL', 'BUYIN', 'PAYOUT' => 'bg-success',
                                                        'CREDIT', 'CASHOUT', 'DROP' => 'bg-danger',
                                                        'VOID' => 'bg-secondary',
                                                        default => 'bg-info'
                                                    };
                                                @endphp
                                                <span class="badge {{ $badgeClass }}">{{ $row->txn_type }}</span>
                                            </td>
                                            <td>
                                                @if($row->payment_medium)
                                                    @php
                                                        $medBadge = $row->payment_medium === 'CASH' ? 'bg-warning text-dark' : 'bg-info text-dark';
                                                    @endphp
                                                    <span class="badge {{ $medBadge }}">{{ $row->payment_medium }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="fw-bold">{{ number_format($row->amount, 2) }}</td>
                                            <td class="text-warning">{{ number_format($row->float_balance, 2) }}</td>
                                            <td>{{ $row->gameday->format('Y-m-d') }}</td>
                                            <td class="small">{{ $row->reference ?? '—' }}</td>
                                            <td class="small">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                                        @elseif($reportType == 'float')
                                            <td>{{ $row->float_id }}</td>
                                            <td>{{ $row->gameTable->table_name ?? 'N/A' }}</td>
                                            <td>{{ $row->gameday->format('Y-m-d') }}</td>
                                            <td>
                                                @if($row->is_open)
                                                    <span class="badge bg-warning text-dark">Open</span>
                                                @else
                                                    <span class="badge bg-success">Closed</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-info fw-semibold">
                                                {{ number_format($row->computed_opening_float, 2) }}
                                            </td>
                                            <td class="text-end fw-semibold {{ $row->is_open ? 'text-muted fst-italic' : 'text-warning' }}">
                                                {{ $row->computed_closing_float !== null ? number_format($row->computed_closing_float, 2) : '—' }}
                                            </td>
                                            <td class="text-end fw-semibold">
                                                @if($row->computed_result !== null)
                                                    <span class="{{ $row->computed_result >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ ($row->computed_result >= 0 ? '+' : '') . number_format($row->computed_result, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="small">{{ $row->opened_at->format('H:i') }}</td>
                                            <td class="small">{{ $row->closed_at ? $row->closed_at->format('H:i') : '—' }}</td>
                                        @elseif($reportType == 'table')
                                            <td>{{ $row->id }}</td>
                                            <td>{{ $row->table_name }}</td>
                                            <td>{{ $row->gameType->name ?? 'N/A' }}</td>
                                            <td class="small font-monospace">{{ $row->active_mac ?? '—' }}</td>
                                            <td>
                                                @if($row->status)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">No data found for the selected criteria.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>
        </div>
    </div>

    <style>
        .table-block + .table-block {
            margin-top: 1.5rem;
        }
        @media print {
            .no-print { display: none !important; }
            .content-wrapper { padding: 0 !important; }
            .card { border: none !important; }
            .only-print { display: block !important; }
            body { background: white !important; color: black !important; }
            .table { color: black !important; }
            .table-dark { --bs-table-bg: transparent !important; }
            .text-warning { color: #856404 !important; }
            .table-block { page-break-inside: avoid; }
        }
        .only-print { display: none; }
    </style>
</x-app-layout>
