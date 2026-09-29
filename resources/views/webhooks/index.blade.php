@extends('layouts.app')

@section('title', 'Webhook Events - CWFSAPI')

@section('page-title', 'Webhooks')

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


    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="fw-semibold mb-1">
                Webhook Events
            </h3>

            <p class="text-muted mb-0">
                View Fullscript webhook events, shipments, tracking information and fulfillment status.
            </p>

        </div>

        <div>

            <span class="badge bg-primary px-3 py-2">

                <i class="bi bi-broadcast me-1"></i>

                Webhooks

            </span>

        </div>

    </div>


    {{-- Webhook Events Card --}}
    <div class="card dashboard-card mb-4">

        <div class="card-body p-4">

            {{-- Card Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4">

                <div class="d-flex align-items-center">

                    <div class="status-icon success me-3">

                        <i class="bi bi-broadcast"></i>

                    </div>

                    <div>

                        <h5 class="mb-1">
                            Fullscript Webhook Events
                        </h5>

                        <small class="text-muted">
                            Events received from Fullscript
                        </small>

                    </div>

                </div>


                <div>

                    <span class="badge bg-success px-3 py-2">

                        <i class="bi bi-check-circle me-1"></i>

                        Event Log

                    </span>

                </div>

            </div>


            {{-- Events Table --}}
            @if($events->count())

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Event ID
                                </th>

                                <th>
                                    Event Type
                                </th>

                                <th>
                                    Fullscript Order
                                </th>

                                <th>
                                    Shipment
                                </th>

                                <th>
                                    Tracking
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Received
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        @foreach($events as $event)

                            <tr>

                                {{-- Event ID --}}
                                <td>

                                    <code class="text-break">
                                        {{ $event->fullscript_event_id ?? '-' }}
                                    </code>

                                </td>


                                {{-- Event Type --}}
                                <td>

                                    <span class="badge bg-light text-dark border">

                                        <i class="bi bi-lightning-charge me-1"></i>

                                        {{ $event->tracking_data['event_type'] ?? 'fulfillment.shipment.shipped' }}

                                    </span>

                                </td>


                                {{-- Fullscript Order --}}
                                <td>

                                    <strong>
                                        {{ $event->fullscript_order_id ?? '-' }}
                                    </strong>

                                </td>


                                {{-- Shipment --}}
                                <td>

                                    {{ $event->tracking_data['shipment_number'] ?? '-' }}

                                </td>


                                {{-- Tracking --}}
                                <td>

                                    @if(!empty($event->tracking_data['tracking_number']))

                                        <strong>
                                            {{ $event->tracking_data['tracking_number'] }}
                                        </strong>

                                        @if(!empty($event->tracking_data['carrier']))

                                            <br>

                                            <small class="text-muted">

                                                <i class="bi bi-truck me-1"></i>

                                                {{ $event->tracking_data['carrier'] }}

                                            </small>

                                        @endif

                                    @else

                                        <span class="text-muted">
                                            -
                                        </span>

                                    @endif

                                </td>


                                {{-- Status --}}
                                <td>

                                    @php
                                        $status = strtolower($event->status ?? '');
                                    @endphp


                                    @if($status === 'shipped')

                                        <span class="badge bg-success">

                                            <i class="bi bi-check-circle me-1"></i>

                                            Shipped

                                        </span>

                                    @elseif($status === 'cancelled')

                                        <span class="badge bg-danger">

                                            <i class="bi bi-x-circle me-1"></i>

                                            Cancelled

                                        </span>

                                    @elseif($status === 'processing')

                                        <span class="badge bg-warning text-dark">

                                            <i class="bi bi-hourglass-split me-1"></i>

                                            Processing

                                        </span>

                                    @else

                                        <span class="badge bg-secondary">

                                            {{ $event->status ?? 'Unknown' }}

                                        </span>

                                    @endif

                                </td>


                                {{-- Received --}}
                                <td>

                                    @if($event->created_at)

                                        <div>
                                            {{ $event->created_at->format('Y-m-d') }}
                                        </div>

                                        <small class="text-muted">
                                            {{ $event->created_at->format('H:i:s') }}
                                        </small>

                                    @else

                                        -

                                    @endif

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>


                {{-- Pagination --}}
                @if($events->hasPages())

                    <div class="d-flex justify-content-between align-items-center mt-4">

                        <div class="text-muted small">

                            Showing
                            <strong>{{ $events->firstItem() }}</strong>
                            to
                            <strong>{{ $events->lastItem() }}</strong>
                            of
                            <strong>{{ $events->total() }}</strong>
                            webhook events

                        </div>

                        <div>

                            {{ $events->links() }}

                        </div>

                    </div>

                @endif


            @else

                {{-- Empty State --}}
                <div class="text-center py-5">

                    <div class="status-icon success mx-auto mb-3">

                        <i class="bi bi-broadcast"></i>

                    </div>

                    <h5 class="mb-2">
                        No Webhook Events
                    </h5>

                    <p class="text-muted mb-0">
                        No Fullscript webhook events have been received yet.
                    </p>

                </div>

            @endif

        </div>

    </div>


    {{-- Webhook Information --}}
    <div class="card dashboard-card">

        <div class="card-body p-4">

            <div class="d-flex align-items-center mb-4">

                <div class="status-icon success me-3">

                    <i class="bi bi-info-circle"></i>

                </div>

                <div>

                    <h5 class="mb-1">
                        Fullscript Webhook
                    </h5>

                    <small class="text-muted">
                        Webhook endpoint information
                    </small>

                </div>

            </div>


            <div class="row g-4">

                {{-- Endpoint --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Webhook Endpoint
                        </small>

                        <code class="text-break">
                            {{ url('/webhooks/fullscript') }}
                        </code>

                    </div>

                </div>


                {{-- Supported Events --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Event
                        </small>

                        <strong>
                            Fullscript Fulfillment Events
                        </strong>

                    </div>

                </div>


                {{-- Method --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            HTTP Method
                        </small>

                        <span class="badge bg-primary">
                            POST
                        </span>

                    </div>

                </div>


                {{-- Status --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Status
                        </small>

                        <span class="badge bg-success">

                            <i class="bi bi-check-circle me-1"></i>

                            Active

                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection