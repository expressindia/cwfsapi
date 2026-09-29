@extends('layouts.app')

@section('title', 'Webhook Events - CWFSAPI')

@section('page-title', 'Webhooks')

@section('content')

<div class="container-fluid px-0">

    {{-- =========================================================
         Page Header
    ========================================================== --}}

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="fw-semibold mb-1">
                Webhook Events
            </h3>

            <p class="text-muted mb-0">
                View webhook events received by CWFSAPI.
            </p>

        </div>

        <div>

            <span class="badge bg-primary px-3 py-2">

                <i class="bi bi-broadcast me-1"></i>

                Webhooks

            </span>

        </div>

    </div>


    {{-- =========================================================
         Flash Messages
    ========================================================== --}}

    @if(session('success'))

        <div
            class="alert alert-success alert-dismissible fade show mb-4"
            role="alert"
        >

            <i class="bi bi-check-circle me-1"></i>

            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    @endif


    @if(session('error'))

        <div
            class="alert alert-danger alert-dismissible fade show mb-4"
            role="alert"
        >

            <i class="bi bi-x-circle me-1"></i>

            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    @endif


    {{-- =========================================================
         Webhook Events Card
    ========================================================== --}}

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
                            Webhook Events
                        </h5>

                        <small class="text-muted">
                            Events received from webhook providers
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


            {{-- =================================================
                 Events Table
            ================================================== --}}

            @if($events->count())

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Provider
                                </th>

                                <th>
                                    Webhook ID
                                </th>

                                <th>
                                    Topic
                                </th>

                                <th>
                                    Processed
                                </th>

                                <th>
                                    Received
                                </th>

                                <th class="text-center">
                                    Payload
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @foreach($events as $event)

                                <tr>


                                    {{-- =================================
                                         ID
                                    ================================== --}}

                                    <td>

                                        <strong>
                                            #{{ $event->id }}
                                        </strong>

                                    </td>


                                    {{-- =================================
                                         Provider
                                    ================================== --}}

                                    <td>

                                        @if($event->provider)

                                            <span class="badge bg-light text-dark border">

                                                <i class="bi bi-cloud-arrow-down me-1"></i>

                                                {{ ucfirst($event->provider) }}

                                            </span>

                                        @else

                                            <span class="text-muted">
                                                -
                                            </span>

                                        @endif

                                    </td>


                                    {{-- =================================
                                         Webhook ID
                                    ================================== --}}

                                    <td>

                                        @if($event->webhook_id)

                                            <code
                                                class="text-break"
                                                style="font-size: 12px;"
                                            >
                                                {{ $event->webhook_id }}
                                            </code>

                                        @else

                                            <span class="text-muted">
                                                -
                                            </span>

                                        @endif

                                    </td>


                                    {{-- =================================
                                         Topic
                                    ================================== --}}

                                    <td>

                                        @if($event->topic)

                                            <span class="badge bg-light text-dark border">

                                                <i class="bi bi-lightning-charge me-1"></i>

                                                {{ $event->topic }}

                                            </span>

                                        @else

                                            <span class="text-muted">
                                                -
                                            </span>

                                        @endif

                                    </td>


                                    {{-- =================================
                                         Processed
                                    ================================== --}}

                                    <td>

                                        @if($event->processed_at)

                                            <span class="badge bg-success">

                                                <i class="bi bi-check-circle me-1"></i>

                                                Processed

                                            </span>

                                            <br>

                                            <small class="text-muted">

                                                {{ $event->processed_at->format('Y-m-d H:i:s') }}

                                            </small>

                                        @else

                                            <span class="badge bg-warning text-dark">

                                                <i class="bi bi-hourglass-split me-1"></i>

                                                Pending

                                            </span>

                                        @endif

                                    </td>


                                    {{-- =================================
                                         Received
                                    ================================== --}}

                                    <td>

                                        @if($event->created_at)

                                            <div>

                                                {{ $event->created_at->format('Y-m-d') }}

                                            </div>

                                            <small class="text-muted">

                                                {{ $event->created_at->format('H:i:s') }}

                                            </small>

                                        @else

                                            <span class="text-muted">
                                                -
                                            </span>

                                        @endif

                                    </td>


                                    {{-- =================================
                                         Payload
                                    ================================== --}}

                                    <td class="text-center">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#payloadModal{{ $event->id }}"
                                        >

                                            <i class="bi bi-code-slash me-1"></i>

                                            View Payload

                                        </button>

                                    </td>


                                </tr>


                                {{-- =================================================
                                     Payload Modal
                                ================================================== --}}

                                <div
                                    class="modal fade"
                                    id="payloadModal{{ $event->id }}"
                                    tabindex="-1"
                                    aria-labelledby="payloadModalLabel{{ $event->id }}"
                                    aria-hidden="true"
                                >

                                    <div
                                        class="modal-dialog modal-xl modal-dialog-scrollable"
                                    >

                                        <div class="modal-content">


                                            {{-- Modal Header --}}
                                            <div class="modal-header">

                                                <div>

                                                    <h5
                                                        class="modal-title"
                                                        id="payloadModalLabel{{ $event->id }}"
                                                    >

                                                        <i class="bi bi-code-slash me-2"></i>

                                                        Webhook Payload

                                                    </h5>


                                                    <small class="text-muted">

                                                        Webhook ID:

                                                        <code>

                                                            {{ $event->webhook_id }}

                                                        </code>

                                                    </small>

                                                </div>


                                                <button
                                                    type="button"
                                                    class="btn-close"
                                                    data-bs-dismiss="modal"
                                                    aria-label="Close"
                                                ></button>

                                            </div>


                                            {{-- Modal Body --}}
                                            <div class="modal-body">


                                                {{-- =================================
                                                     Event Information
                                                ================================== --}}

                                                <div class="row g-3 mb-4">


                                                    {{-- Provider --}}
                                                    <div class="col-md-6">

                                                        <div class="border rounded p-3 h-100">

                                                            <small class="text-muted d-block mb-2">

                                                                Provider

                                                            </small>

                                                            <strong>

                                                                {{ $event->provider ?? '-' }}

                                                            </strong>

                                                        </div>

                                                    </div>


                                                    {{-- Webhook ID --}}
                                                    <div class="col-md-6">

                                                        <div class="border rounded p-3 h-100">

                                                            <small class="text-muted d-block mb-2">

                                                                Webhook ID

                                                            </small>

                                                            <code class="text-break">

                                                                {{ $event->webhook_id ?? '-' }}

                                                            </code>

                                                        </div>

                                                    </div>


                                                    {{-- Topic --}}
                                                    <div class="col-md-6">

                                                        <div class="border rounded p-3 h-100">

                                                            <small class="text-muted d-block mb-2">

                                                                Topic

                                                            </small>

                                                            <strong>

                                                                {{ $event->topic ?? '-' }}

                                                            </strong>

                                                        </div>

                                                    </div>


                                                    {{-- Processed --}}
                                                    <div class="col-md-6">

                                                        <div class="border rounded p-3 h-100">

                                                            <small class="text-muted d-block mb-2">

                                                                Processed

                                                            </small>


                                                            @if($event->processed_at)

                                                                <span class="badge bg-success">

                                                                    <i class="bi bi-check-circle me-1"></i>

                                                                    Processed

                                                                </span>


                                                                <div class="mt-2 text-muted small">

                                                                    {{ $event->processed_at->format('Y-m-d H:i:s') }}

                                                                </div>

                                                            @else

                                                                <span class="badge bg-warning text-dark">

                                                                    <i class="bi bi-hourglass-split me-1"></i>

                                                                    Pending

                                                                </span>

                                                            @endif

                                                        </div>

                                                    </div>


                                                    {{-- Received --}}
                                                    <div class="col-md-6">

                                                        <div class="border rounded p-3 h-100">

                                                            <small class="text-muted d-block mb-2">

                                                                Received

                                                            </small>

                                                            <strong>

                                                                {{ $event->created_at?->format('Y-m-d H:i:s') ?? '-' }}

                                                            </strong>

                                                        </div>

                                                    </div>


                                                    {{-- Database ID --}}
                                                    <div class="col-md-6">

                                                        <div class="border rounded p-3 h-100">

                                                            <small class="text-muted d-block mb-2">

                                                                Database ID

                                                            </small>

                                                            <strong>

                                                                #{{ $event->id }}

                                                            </strong>

                                                        </div>

                                                    </div>

                                                </div>


                                                {{-- =================================
                                                     Complete Payload
                                                ================================== --}}

                                                <div>

                                                    <div
                                                        class="d-flex justify-content-between align-items-center mb-2"
                                                    >

                                                        <h6 class="mb-0 fw-semibold">

                                                            Complete Payload

                                                        </h6>


                                                        <span class="badge bg-secondary">

                                                            JSON

                                                        </span>

                                                    </div>


                                                    <div
                                                        class="border rounded bg-dark p-3"
                                                        style="
                                                            max-height: 600px;
                                                            overflow-y: auto;
                                                        "
                                                    >

                                                        <pre
                                                            class="text-light mb-0"
                                                            style="
                                                                white-space: pre-wrap;
                                                                word-break: break-word;
                                                                font-size: 13px;
                                                            "
                                                        ><code>{{ json_encode(
                                                            $event->payload,
                                                            JSON_PRETTY_PRINT |
                                                            JSON_UNESCAPED_SLASHES |
                                                            JSON_UNESCAPED_UNICODE
                                                        ) }}</code></pre>

                                                    </div>

                                                </div>

                                            </div>


                                            {{-- Modal Footer --}}
                                            <div class="modal-footer">

                                                <small class="text-muted me-auto">

                                                    Received:

                                                    {{ $event->created_at?->format('Y-m-d H:i:s') ?? '-' }}

                                                </small>


                                                <button
                                                    type="button"
                                                    class="btn btn-secondary"
                                                    data-bs-dismiss="modal"
                                                >

                                                    Close

                                                </button>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                {{-- =================================================
                     Pagination
                ================================================== --}}

                @if($events->hasPages())

                    <div
                        class="d-flex justify-content-between align-items-center mt-4"
                    >

                        <div class="text-muted small">

                            Showing

                            <strong>
                                {{ $events->firstItem() }}
                            </strong>

                            to

                            <strong>
                                {{ $events->lastItem() }}
                            </strong>

                            of

                            <strong>
                                {{ $events->total() }}
                            </strong>

                            webhook events

                        </div>


                        <div>

                            {{ $events->links() }}

                        </div>

                    </div>

                @endif


            @else


                {{-- =================================================
                     Empty State
                ================================================== --}}

                <div class="text-center py-5">

                    <div class="status-icon success mx-auto mb-3">

                        <i class="bi bi-broadcast"></i>

                    </div>


                    <h5 class="mb-2">

                        No Webhook Events

                    </h5>


                    <p class="text-muted mb-0">

                        No webhook events have been received yet.

                    </p>

                </div>

            @endif

        </div>

    </div>


    {{-- =========================================================
         Webhook Information
    ========================================================== --}}

    <div class="card dashboard-card">

        <div class="card-body p-4">


            {{-- Card Header --}}
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


            {{-- Information --}}
            <div class="row g-4">


                {{-- Endpoint --}}
                <div class="col-md-6">

                    <div class="border rounded p-3 h-100">

                        <small class="text-muted d-block mb-2">

                            Webhook Endpoint

                        </small>

                        <code class="text-break">

                            {{ url('/webhooks/fullscript') }}

                        </code>

                    </div>

                </div>


                {{-- Provider --}}
                <div class="col-md-6">

                    <div class="border rounded p-3 h-100">

                        <small class="text-muted d-block mb-2">

                            Provider

                        </small>

                        <strong>

                            Fullscript

                        </strong>

                    </div>

                </div>


                {{-- HTTP Method --}}
                <div class="col-md-6">

                    <div class="border rounded p-3 h-100">

                        <small class="text-muted d-block mb-2">

                            HTTP Method

                        </small>

                        <span class="badge bg-primary">

                            <i class="bi bi-arrow-down-circle me-1"></i>

                            POST

                        </span>

                    </div>

                </div>


                {{-- Status --}}
                <div class="col-md-6">

                    <div class="border rounded p-3 h-100">

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