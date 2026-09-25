<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title> @yield('title', 'CWFSAPI') </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body>

<div class="app-wrapper">

    {{-- Sidebar --}}
    @include('partials.sidebar')

    {{-- Main Content --}}
    <div class="main-content">

        {{-- Topbar --}}
        <header class="topbar">

            <div class="d-flex align-items-center">

                <button
                    class="btn btn-outline-secondary sidebar-toggle me-3"
                    type="button"
                    id="sidebarToggle">

                    <i class="bi bi-list"></i>

                </button>

                <h5 class="mb-0">
                    @yield('page-title', 'Dashboard')
                </h5>

            </div>

            <div class="d-flex align-items-center">

                <span class="text-muted me-3">
                    CWFSAPI
                </span>

                <button class="btn btn-light">
                    Account
                </button>

            </div>

        </header>

        {{-- Page Content --}}
        <main class="page-content">

            @yield('content')

        </main>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const toggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');

        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
            });
        }

    });
</script>

@stack('scripts')

</body>
</html>