<x-app-layout>

    <div class="container-fluid py-4">

        {{-- Page Header --}}
        <div class="d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-shield-exclamation text-warning fs-2"></i>
            <div>
                <h4 class="mb-0 fw-bold">System Reset Utility</h4>
                <small class="text-secondary">Authorized personnel only — these actions cannot be undone.</small>
            </div>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-x-circle-fill me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Pre-flight Snapshot --}}
        <div class="card bg-secondary bg-opacity-10 border border-secondary mb-4">
            <div class="card-header border-secondary d-flex align-items-center gap-2">
                <i class="bi bi-database-check text-info"></i>
                <span class="fw-semibold">Current Data Snapshot</span>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($snapshot as $table => $count)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="d-flex justify-content-between bg-dark rounded px-3 py-2 small">
                                <span class="text-secondary font-monospace">{{ $table }}</span>
                                <span class="fw-bold {{ $count > 0 ? 'text-warning' : 'text-success' }}">
                                    {{ number_format($count) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- ─────────────────────────────────────────────────────── --}}
            {{-- RESET TYPE 1 — API DATA RESET                          --}}
            {{-- ─────────────────────────────────────────────────────── --}}
            <div class="col-lg-6">
                <div class="card border border-warning h-100">
                    <div class="card-header bg-warning bg-opacity-10 border-warning d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-counterclockwise text-warning fs-5"></i>
                        <span class="fw-bold text-warning">API Data Reset</span>
                        <span class="badge bg-warning text-dark ms-auto">Type 1</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">

                        <p class="text-secondary mb-0 small">
                            Clears all runtime data generated through the API. Configuration is fully preserved.
                        </p>

                        {{-- What gets cleared --}}
                        <div>
                            <p class="small fw-semibold text-danger mb-1">🗑 Cleared:</p>
                            <ul class="small text-secondary mb-0 ps-3">
                                <li>All game history (baccarat, blackjack, dragon tiger, etc.)</li>
                                <li>All table ledger transactions</li>
                                <li>All table float sessions</li>
                                <li>All game day records</li>
                                <li>Resets <code>bet_index</code> → 1 and clears <code>active_mac</code> on all tables</li>
                            </ul>
                        </div>

                        {{-- What is kept --}}
                        <div>
                            <p class="small fw-semibold text-success mb-1">✔ Preserved:</p>
                            <ul class="small text-secondary mb-0 ps-3">
                                <li>Game tables & all configuration (presets, payout rules)</li>
                                <li>Users, roles & permissions</li>
                                <li>Chips, game types, shoe types</li>
                            </ul>
                        </div>

                        <hr class="border-secondary my-1">

                        <form method="POST"
                              action="{{ route('utilities.reset.api-data') }}"
                              x-data="{ confirm_val: '' }"
                              @submit.prevent="submitApiReset($el)">
                            @csrf

                            <label class="form-label small text-warning fw-semibold">
                                Type <kbd class="bg-warning text-dark">RESET API DATA</kbd> to confirm:
                            </label>
                            <input
                                type="text"
                                name="confirmation"
                                class="form-control form-control-sm bg-dark text-white border-warning mb-3 @error('confirmation') is-invalid @enderror"
                                placeholder="RESET API DATA"
                                x-model="confirm_val"
                                autocomplete="off"
                            >
                            @error('confirmation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <button
                                type="submit"
                                class="btn btn-warning w-100"
                                :disabled="confirm_val !== 'RESET API DATA'"
                            >
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Execute API Data Reset
                            </button>
                        </form>

                    </div>
                </div>
            </div>

            {{-- ─────────────────────────────────────────────────────── --}}
            {{-- RESET TYPE 2 — FULL DB RESET                           --}}
            {{-- ─────────────────────────────────────────────────────── --}}
            <div class="col-lg-6">
                <div class="card border border-danger h-100">
                    <div class="card-header bg-danger bg-opacity-10 border-danger d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                        <span class="fw-bold text-danger">Full DB Reset</span>
                        <span class="badge bg-danger ms-auto">Type 2 — Destructive</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">

                        <p class="text-secondary mb-0 small">
                            Wipes the entire database and re-seeds to factory defaults. This logs you out immediately.
                        </p>

                        {{-- What gets cleared --}}
                        <div>
                            <p class="small fw-semibold text-danger mb-1">🗑 Everything is wiped:</p>
                            <ul class="small text-secondary mb-0 ps-3">
                                <li>All game history, ledger, floats, game days</li>
                                <li>All game tables, configs, presets, payout rules</li>
                                <li>All users, roles, permissions</li>
                                <li>Chips, game types, shoe types</li>
                            </ul>
                        </div>

                        {{-- What is restored --}}
                        <div>
                            <p class="small fw-semibold text-success mb-1">✔ Restored by seeder:</p>
                            <ul class="small text-secondary mb-0 ps-3">
                                <li>Admin user (<code>admin / admin@123</code>)</li>
                                <li>Default roles & permissions</li>
                                <li>Default game types, shoe types, payout rules</li>
                            </ul>
                        </div>

                        <div class="alert alert-danger py-2 mb-0 small">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Irreversible.</strong> You will be logged out after execution. Log back in with the default admin credentials.
                        </div>

                        <form method="POST"
                              action="{{ route('utilities.reset.full-db') }}"
                              x-data="{ confirm_val: '' }"
                              @submit.prevent="submitFullReset($el)">
                            @csrf

                            <label class="form-label small text-danger fw-semibold">
                                Type <kbd class="bg-danger text-white">FULL DB RESET</kbd> to confirm:
                            </label>
                            <input
                                type="text"
                                name="confirmation"
                                class="form-control form-control-sm bg-dark text-white border-danger mb-3 @error('confirmation') is-invalid @enderror"
                                placeholder="FULL DB RESET"
                                x-model="confirm_val"
                                autocomplete="off"
                            >
                            @error('confirmation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <button
                                type="submit"
                                class="btn btn-danger w-100"
                                :disabled="confirm_val !== 'FULL DB RESET'"
                            >
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Execute Full DB Reset
                            </button>
                        </form>

                    </div>
                </div>
            </div>

        </div>{{-- /row --}}
    </div>

    @push('scripts')
    <script>
        function submitApiReset(form) {
            Swal.fire({
                title: 'API Data Reset',
                html: 'This will wipe all game history, ledger, float and game day records.<br><br><strong>Are you sure?</strong>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'Yes, reset API data',
                cancelButtonText: 'Cancel',
                background: '#1a1a2e',
                color: '#fff',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function submitFullReset(form) {
            Swal.fire({
                title: '⚠️ Full DB Reset',
                html: 'This will <strong>permanently wipe everything</strong> in the database and re-seed to factory defaults.<br><br>You will be logged out immediately.<br><br><strong>This cannot be undone.</strong>',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, wipe everything',
                cancelButtonText: 'Cancel',
                background: '#1a1a2e',
                color: '#fff',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
    @endpush

</x-app-layout>
