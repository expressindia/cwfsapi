<aside class="sidebar">

    <a href="{{ route('dashboard') }}"
       class="sidebar-brand">

        CWFSAPI

    </a>


    <div class="sidebar-section">
        Main
    </div>

    <nav class="nav flex-column">

        <a href="{{ route('dashboard') }}"
           class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">

            <i class="bi bi-speedometer2"></i>

            Dashboard

        </a>

    </nav>


    <div class="sidebar-section">
        Integrations
    </div>

    <nav class="nav flex-column">

        <a href="{{ route('fullscript.status') }}"
           class="nav-link {{ request()->routeIs('fullscript.*') ? 'active' : '' }}">

            <i class="bi bi-link-45deg"></i>

            Fullscript

        </a>

        <a href="{{ route('shopify.index') }}"
           class="nav-link">

            <i class="bi bi-shop"></i>

            Shopify

        </a>

    </nav>


    <div class="sidebar-section">
        Sync
    </div>

    <nav class="nav flex-column">

        <a href="#"
           class="nav-link">

            <i class="bi bi-box-seam"></i>

            Products

        </a>

        <a href="#"
           class="nav-link">

            <i class="bi bi-boxes"></i>

            Inventory

        </a>

        <a href="#"
           class="nav-link">

            <i class="bi bi-cart3"></i>

            Orders

        </a>

        <a href="#"
           class="nav-link">

            <i class="bi bi-truck"></i>

            Fulfillments

        </a>

    </nav>


    <div class="sidebar-section">
        System
    </div>

    <nav class="nav flex-column">

        <a href="#"
           class="nav-link">

            <i class="bi bi-clock-history"></i>

            Sync Logs

        </a>
        <a href="{{ route('logs') }}" class="nav-link"> <i class="bi bi-clock-history"></i> App Logs  </a>

        <a href="#"
           class="nav-link">

            <i class="bi bi-gear"></i>

            Settings

        </a>

    </nav>

</aside>