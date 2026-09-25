@extends('layouts.app')

@section('title', 'Shopify Status - CWFSAPI')

@section('page-title', 'Shopify')

@section('content')

<div class="container-fluid px-0">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-1"></i>
            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-x-circle me-1"></i>
            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>
        </div>
    @endif

    @if(isset($error))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $error }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>
        </div>
    @endif


    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h3 class="fw-semibold mb-1">
                Shopify Status
            </h3>

            <p class="text-muted mb-0">
                View the current Shopify connection and fulfillment service status.
            </p>
        </div>

        @if($status['connected'] ?? false)

            <span class="badge bg-success px-3 py-2">
                <i class="bi bi-check-circle me-1"></i>
                Connected
            </span>

        @else

            <span class="badge bg-danger px-3 py-2">
                <i class="bi bi-x-circle me-1"></i>
                Not Connected
            </span>

        @endif

    </div>


    {{-- Shopify Connection Card --}}
    <div class="card dashboard-card mb-4">

        <div class="card-body p-4">

            <div class="d-flex align-items-center mb-4">

                <div class="status-icon
                    {{ ($status['connected'] ?? false) ? 'success' : 'danger' }}
                    me-3">

                    <i class="bi bi-shop"></i>

                </div>

                <div>

                    <h5 class="mb-1">
                        Shopify API
                    </h5>

                    <small class="text-muted">
                        Shopify store connection information
                    </small>

                </div>

            </div>


            <div class="row g-4">

                {{-- Connection --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Connection Status
                        </small>

                        @if($status['connected'] ?? false)

                            <span class="badge bg-success">
                                <i class="bi bi-check-circle me-1"></i>
                                Connected
                            </span>

                        @else

                            <span class="badge bg-danger">
                                <i class="bi bi-x-circle me-1"></i>
                                Not Connected
                            </span>

                        @endif

                    </div>

                </div>


                {{-- Store Name --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Store
                        </small>

                        <strong>
                            {{ $status['shop_name'] ?? 'N/A' }}
                        </strong>

                    </div>

                </div>


                {{-- Store Domain --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Store Domain
                        </small>

                        <strong>
                            {{ $status['shop_domain'] ?? 'N/A' }}
                        </strong>

                    </div>

                </div>


                {{-- API Version --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            API Version
                        </small>

                        <strong>
                            {{ config('shopify.api_version', '2026-07') }}
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- Fulfillment Service Card --}}
    <div class="card dashboard-card">

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div class="d-flex align-items-center">

                    <div class="status-icon
                        {{ !empty($status['service']) ? 'success' : 'danger' }}
                        me-3">

                        <i class="bi bi-box-seam"></i>

                    </div>

                    <div>

                        <h5 class="mb-1">
                            Fulfillment Service
                        </h5>

                        <small class="text-muted">
                            Shopify fulfillment service configuration
                        </small>

                    </div>

                </div>


                @if(!empty($status['service']))

                    <span class="badge bg-success px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i>
                        Registered
                    </span>

                @else

                    <span class="badge bg-warning text-dark px-3 py-2">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Not Registered
                    </span>

                @endif

            </div>


            <div class="row g-4">

                {{-- Service Name --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Service Name
                        </small>

                        <strong>
                            {{ $status['service_name'] ?? 'FSWarehouse' }}
                        </strong>

                    </div>

                </div>


                {{-- Service Status --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Service Status
                        </small>

                        @if(!empty($status['service']))

                            <span class="badge bg-success">
                                <i class="bi bi-check-circle me-1"></i>
                                Registered
                            </span>

                        @else

                            <span class="badge bg-warning text-dark">
                                Not Registered
                            </span>

                        @endif

                    </div>

                </div>


                @if(!empty($status['service']))

                    {{-- Fulfillment Service ID --}}
                    <div class="col-md-6">

                        <div class="border rounded p-3">

                            <small class="text-muted d-block mb-2">
                                Fulfillment Service ID
                            </small>

                            <code class="text-break">
                                {{ $status['service']['id'] ?? 'N/A' }}
                            </code>

                        </div>

                    </div>


                    {{-- Handle --}}
                    <div class="col-md-6">

                        <div class="border rounded p-3">

                            <small class="text-muted d-block mb-2">
                                Handle
                            </small>

                            <strong>
                                {{ $status['service']['handle'] ?? 'N/A' }}
                            </strong>

                        </div>

                    </div>


                    {{-- Location --}}
                    <div class="col-md-6">

                        <div class="border rounded p-3">

                            <small class="text-muted d-block mb-2">
                                Location
                            </small>

                            <strong>
                                {{ $status['service']['location']['name'] ?? 'N/A' }}
                            </strong>

                        </div>

                    </div>


                    {{-- Location ID --}}
                    <div class="col-md-6">

                        <div class="border rounded p-3">

                            <small class="text-muted d-block mb-2">
                                Location ID
                            </small>

                            <code class="text-break">
                                {{ $status['service']['location']['id'] ?? 'N/A' }}
                            </code>

                        </div>

                    </div>


                    {{-- Tracking Support --}}
                    <div class="col-md-6">

                        <div class="border rounded p-3">

                            <small class="text-muted d-block mb-2">
                                Tracking Support
                            </small>

                            @if($status['service']['trackingSupport'] ?? false)

                                <span class="badge bg-success">
                                    Enabled
                                </span>

                            @else

                                <span class="badge bg-secondary">
                                    Disabled
                                </span>

                            @endif

                        </div>

                    </div>


                    {{-- Inventory Management --}}
                    <div class="col-md-6">

                        <div class="border rounded p-3">

                            <small class="text-muted d-block mb-2">
                                Inventory Management
                            </small>

                            @if($status['service']['inventoryManagement'] ?? false)

                                <span class="badge bg-success">
                                    Enabled
                                </span>

                            @else

                                <span class="badge bg-secondary">
                                    Disabled
                                </span>

                            @endif

                        </div>

                    </div>

                @endif

            </div>


            {{-- Register Service --}}
            @if(empty($status['service']))

                <div class="border-top mt-4 pt-4">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="mb-1">
                                Register FSWarehouse
                            </h6>

                            <small class="text-muted">
                                Register the FSWarehouse fulfillment service with Shopify.
                                Shopify will create the associated fulfillment location.
                            </small>

                        </div>


                        <form
                            method="POST"
                            action="{{ route('shopify.fulfillment.register') }}"
                        >

                            @csrf

                            <button
                                type="submit"
                                class="btn btn-success"
                            >

                                <i class="bi bi-plus-circle me-1"></i>

                                Register FSWarehouse

                            </button>

                        </form>

                    </div>

                </div>

            @else

                {{-- Already Registered --}}
                <div class="border-top mt-4 pt-4">

                    <div class="alert alert-success mb-0">

                        <i class="bi bi-check-circle me-2"></i>

                        <strong>FSWarehouse is registered.</strong>

                        The fulfillment service and location IDs have been saved
                        for this Shopify store.

                    </div>

                </div>

            @endif

        </div>

    </div>

</div>

@endsection