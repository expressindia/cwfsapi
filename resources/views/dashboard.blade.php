@extends('layouts.app')

@section('title', 'Dashboard - CWFSAPI')

@section('page-title', 'Dashboard')

@section('content')

<div class="container-fluid px-0">

    {{-- Page Heading --}}
    <div class="mb-4">

        <h3 class="fw-semibold mb-1">
            Dashboard
        </h3>

        <p class="text-muted mb-0">
            Monitor your Fullscript and Shopify integrations.
        </p>

    </div>


    {{-- Integration Status --}}
    <div class="row g-4 mb-4">

        {{-- Fullscript --}}
        <div class="col-xl-6 col-md-6">

            <div class="card dashboard-card h-100">

                <div class="card-body p-4">

                    <div class="d-flex justify-content-between">

                        <div class="d-flex align-items-center">

                            <div class="status-icon
                                {{ $fullscriptConnected ? 'success' : 'danger' }}
                                me-3">

                                <i class="bi bi-link-45deg"></i>

                            </div>

                            <div>

                                <h5 class="mb-1">
                                    Fullscript
                                </h5>

                                <small class="text-muted">
                                    API Integration
                                </small>

                            </div>

                        </div>


                        @if($fullscriptConnected)

                            <span class="badge bg-success-subtle text-success px-3 py-2">
                                Connected
                            </span>

                        @else

                            <span class="badge bg-danger-subtle text-danger px-3 py-2">
                                Not Connected
                            </span>

                        @endif

                    </div>


                    <hr class="my-4">


                    @if($fullscriptConnected)

                        <div class="row">

                            <div class="col-md-6">

                                <small class="text-muted d-block">
                                    Environment
                                </small>

                                <strong>
                                    Sandbox
                                </strong>

                            </div>


                            <div class="col-md-6">

                                <small class="text-muted d-block">
                                    Token Status
                                </small>

                                <strong class="text-success">
                                    Active
                                </strong>

                            </div>

                        </div>


                        @if($fullscript?->expires_at)

                            <div class="mt-3">

                                <small class="text-muted d-block">
                                    Token Expires
                                </small>

                                <strong>
                                    {{ $fullscript->expires_at->format('M d, Y h:i A') }}
                                </strong>

                            </div>

                        @endif


                        <div class="mt-4">

                            <a href="{{ route('fullscript.status') }}"
                               class="btn btn-outline-primary">

                                View Details

                            </a>

                        </div>

                    @else

                        <p class="text-muted">

                            Your application is currently not connected
                            to Fullscript.

                        </p>

                        {{-- <a href="{{ route('fullscript.connect') }}"
                        target="_top"
                        class="btn btn-primary">

                            <i class="bi bi-plug me-1"></i>

                            Connect Fullscript

                        </a> --}}
                        <button
                            type="button"
                            id="connect-fullscript"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-plug me-1"></i>
                            Connect Fullscript
                        </button>

                    @endif

                </div>

            </div>

        </div>


        {{-- Shopify --}}
        <div class="col-xl-6 col-md-6">

            <div class="card dashboard-card h-100">

                <div class="card-body p-4">

                    <div class="d-flex justify-content-between">

                        <div class="d-flex align-items-center">

                            <div class="status-icon warning me-3">

                                <i class="bi bi-shop"></i>

                            </div>

                            <div>

                                <h5 class="mb-1">
                                    Shopify
                                </h5>

                                <small class="text-muted">
                                    Store Integration
                                </small>

                            </div>

                        </div>


                        <span class="badge bg-success-subtle text-success px-3 py-2">
                            Connected
                        </span>

                    </div>


                    <hr class="my-4">


                    <p class="text-muted">

                        Shopify integration with store

                    </p>


                    <button class="btn btn-outline-secondary"
                            disabled>

                        CWFSAPI Development Store

                    </button>

                </div>

            </div>

        </div>

    </div>


    {{-- Sync Overview --}}
    <div class="card dashboard-card">

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h5 class="mb-1">
                        Sync Overview
                    </h5>

                    <p class="text-muted mb-0">
                        Product and order synchronization status.
                    </p>

                </div>

            </div>


            <hr>


            <div class="row g-4">

                <div class="col-md-3">

                    <div class="p-3 bg-light rounded">

                        <small class="text-muted">
                            Products
                        </small>

                        <h4 class="mt-2 mb-0">
                            0
                        </h4>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="p-3 bg-light rounded">

                        <small class="text-muted">
                            Inventory
                        </small>

                        <h4 class="mt-2 mb-0">
                            0
                        </h4>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="p-3 bg-light rounded">

                        <small class="text-muted">
                            Orders
                        </small>

                        <h4 class="mt-2 mb-0">
                            0
                        </h4>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="p-3 bg-light rounded">

                        <small class="text-muted">
                            Last Sync
                        </small>

                        <h4 class="mt-2 mb-0">
                            Never
                        </h4>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const button = document.getElementById('connect-fullscript');

    if (!button) {
        return;
    }

    button.addEventListener('click', async function () {

        button.disabled = true;

        try {

            const response = await fetch(
                '{{ route('fullscript.connect') }}',
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}`
                );
            }

            const data = await response.json();

            if (!data.url) {
                throw new Error(
                    'Fullscript authorization URL was not returned.'
                );
            }

            window.open(
                data.url,
                '_top'
            );

        } catch (error) {

            console.error(
                'Fullscript connection error:',
                error
            );

            alert(
                'Unable to connect to Fullscript. Please try again.'
            );

            button.disabled = false;
        }
    });

});
</script>
@endpush