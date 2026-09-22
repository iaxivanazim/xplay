<div id="sidebar" class="sidebar">

    <div class="sidebar-header text-center">
        <div class="brand-text text-center">
            <img src="{{ asset('images/logo.png') }}"
                alt="xplay Logo"
                style="max-width:140px;">
        </div>
    </div>

    <ul class="nav flex-column mt-4">

        <li class="nav-item">
            <a href="/dashboard" class="nav-link">
                <i class="bi bi-speedometer2"></i>
                <span class="link-text">Dashboard</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/tabs" class="nav-link">
                <i class="bi bi-tablet-landscape"></i>
                <span class="link-text">Tab Config</span>
            </a>
        </li>

        <!-- <li class="nav-item">
            <a href="/payout_rules" class="nav-link">
                <i class="bi bi-currency-dollar"></i>
                <span class="link-text">Payout Rules</span>
            </a>
        </li> -->

        <li class="nav-item">
            <a href="/chips" class="nav-link">
                <i class="bi bi-cash-stack"></i>
                <span class="link-text">Chip Config</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/history" class="nav-link">
                <i class="bi bi-clock-history"></i>
                <span class="link-text">History</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/ledger" class="nav-link">
                <i class="bi bi-wallet"></i>
                <span class="link-text">Ledger</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/reports" class="nav-link">
                <i class="bi bi-bar-chart"></i>
                <span class="link-text">Reports</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/roles" class="nav-link">
                <i class="bi bi-shield-lock"></i>
                <span class="link-text">Roles</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="/users" class="nav-link">
                <i class="bi bi-people"></i>
                <span class="link-text">Users</span>
            </a>
        </li>

        @if(auth()->user()?->hasPermission('manage-resets'))
        <li class="nav-item mt-2">
            <a href="{{ route('utilities.reset') }}" class="nav-link text-warning">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span class="link-text">Reset Utility</span>
            </a>
        </li>
        @endif

    </ul>
</div>