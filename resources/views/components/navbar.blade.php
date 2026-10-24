<nav class="navbar navbar-dark bg-black py-2 px-3">
    <div class="container-fluid d-flex align-items-center gap-3">
        <button class="btn btn-outline-warning btn-sm d-flex align-items-center justify-content-center" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>

        <!--  -->

        <div class="d-flex align-items-center">
            <div class="btn-group" role="group">
                <button type="button"
                    class="btn btn-sm btn-outline-warning dropdown-toggle px-3 py-1"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="true"
                    aria-expanded="false">
                    <span class="me-2">
                        {{ auth()->user()->username ?? 'Guest' }}
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark mt-2">
                    <li>
                        <a class="dropdown-item text-white" href="{{ route('profile.edit') }}">
                            {{ __('Profile') }}
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
