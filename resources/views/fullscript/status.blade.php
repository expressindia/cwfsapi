@extends('layouts.app')

@section('title', 'Fullscript Status - CWFSAPI')

@section('page-title', 'Fullscript')

@section('content')

<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h3 class="fw-semibold mb-1">
                Fullscript Status
            </h3>

            <p class="text-muted mb-0">
                View the current Fullscript API connection status.
            </p>
        </div>

        @if($connected)
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


    {{-- Status Card --}}
    <div class="card dashboard-card">

        <div class="card-body p-4">

            <div class="d-flex align-items-center mb-4">

                <div class="status-icon
                    {{ $connected ? 'success' : 'danger' }}
                    me-3">

                    <i class="bi bi-link-45deg"></i>

                </div>

                <div>

                    <h5 class="mb-1">
                        Fullscript API
                    </h5>

                    <small class="text-muted">
                        OAuth connection information
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

                        @if($connected)

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


                {{-- Environment --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Environment
                        </small>

                        <strong>
                            {{ ucfirst($environment) }}
                        </strong>

                    </div>

                </div>


                {{-- Expiration --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Token Expires At
                        </small>

                        <strong>

                            {{ $expires_at
                                ? \Carbon\Carbon::parse($expires_at)->format('M d, Y h:i A')
                                : 'N/A' }}

                        </strong>

                    </div>

                </div>


                {{-- Token Status --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Token Status
                        </small>

                        @if($expired)

                            <span class="badge bg-danger">
                                Expired
                            </span>

                        @else

                            <span class="badge bg-success">
                                Active
                            </span>

                        @endif

                    </div>

                </div>


                {{-- Scope --}}
                <div class="col-12">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            OAuth Scope
                        </small>

                        @if($scope)

                            <code>
                                {{ $scope }}
                            </code>

                        @else

                            <span class="text-muted">
                                No scope information available.
                            </span>

                        @endif

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection