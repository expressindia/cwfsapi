@extends('layouts.app')

@section('title', 'Fulfillment Details - CWFSAPI')

@section('page-title', 'Fulfillment Details')

@section('content')

<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="fw-semibold mb-1">
                Fulfillment Details
            </h3>

            <p class="text-muted mb-0">
                Complete fulfillment information and Fullscript response.
            </p>

        </div>


        <div>

            <a
                href="{{ route('fulfillments.index') }}"
                class="btn btn-outline-secondary"
            >

                <i class="bi bi-arrow-left me-1"></i>

                Back to Fulfillments

            </a>

        </div>

    </div>


    {{-- =========================================================
         Order Information
    ========================================================== --}}

    <div class="card dashboard-card mb-4">

        <div class="card-body p-4">

            <div class="d-flex align-items-center mb-4">

                <div class="status-icon success me-3">

                    <i class="bi bi-box-seam"></i>

                </div>

                <div>

                    <h5 class="mb-1">
                        Order Information
                    </h5>

                    <small class="text-muted">
                        Shopify and Fullscript order information
                    </small>

                </div>

            </div>


            <div class="row g-4">

                {{-- ID --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            ID
                        </small>

                        <strong>
                            #{{ $fulfillment->id }}
                        </strong>

                    </div>

                </div>


                {{-- Shopify Order Name --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Shopify Order Name
                        </small>

                        <strong>
                            {{ $fulfillment->shopify_order_name ?? '-' }}
                        </strong>

                    </div>

                </div>


                {{-- Shopify Order --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Shopify Order
                        </small>

                        <code class="text-break">
                            {{ $fulfillment->shopify_order_id ?? '-' }}
                        </code>

                    </div>

                </div>


                {{-- Shopify Fulfillment Order --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Shopify Fulfillment Order
                        </small>

                        <code class="text-break">
                            {{ $fulfillment->shopify_fulfillment_order_id ?? '-' }}
                        </code>

                    </div>

                </div>


                {{-- Fullscript Order --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Fullscript Order
                        </small>

                        <strong>
                            {{ $fulfillment->fullscript_order_id ?? '-' }}
                        </strong>

                    </div>

                </div>


                {{-- Status --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Status
                        </small>

                        <span class="badge bg-secondary">
                            {{ $fulfillment->status ?? 'Unknown' }}
                        </span>

                    </div>

                </div>


                {{-- Created --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Created
                        </small>

                        <strong>
                            {{ $fulfillment->created_at?->format('Y-m-d H:i:s') ?? '-' }}
                        </strong>

                    </div>

                </div>


            </div>

        </div>

    </div>


    {{-- =========================================================
         Fullscript Event
    ========================================================== --}}

    <div class="card dashboard-card mb-4">

        <div class="card-body p-4">

            <div class="d-flex align-items-center mb-4">

                <div class="status-icon success me-3">

                    <i class="bi bi-broadcast"></i>

                </div>

                <div>

                    <h5 class="mb-1">
                        Fullscript Event
                    </h5>

                    <small class="text-muted">
                        Fullscript webhook event information
                    </small>

                </div>

            </div>


            <div class="row g-4">

                {{-- Fullscript Event ID --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Fullscript Event ID
                        </small>

                        <code class="text-break">
                            {{ $fulfillment->fullscript_event_id ?? '-' }}
                        </code>

                    </div>

                </div>


                {{-- Event Type --}}
                <div class="col-md-6">

                    <div class="border rounded p-3">

                        <small class="text-muted d-block mb-2">
                            Event Type
                        </small>

                        <strong>
                            {{ $fulfillment->tracking_data['event_type'] ?? '-' }}
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =========================================================
         Tracking Data
    ========================================================== --}}

    <div class="card dashboard-card mb-4">

        <div class="card-body p-4">

            <div class="d-flex align-items-center mb-4">

                <div class="status-icon success me-3">

                    <i class="bi bi-truck"></i>

                </div>

                <div>

                    <h5 class="mb-1">
                        Tracking Data
                    </h5>

                    <small class="text-muted">
                        Shipment and tracking information received from Fullscript
                    </small>

                </div>

            </div>


            <div
                class="border rounded bg-dark p-3"
                style="max-height: 500px; overflow-y: auto;"
            >

                <pre
                    class="text-light mb-0"
                    style="
                        white-space: pre-wrap;
                        word-break: break-word;
                        font-size: 13px;
                    "
                ><code>{{ json_encode(
                    $fulfillment->tracking_data,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                ) }}</code></pre>

            </div>

        </div>

    </div>


    {{-- =========================================================
         Fullscript Response
    ========================================================== --}}

    <div class="card dashboard-card">

        <div class="card-body p-4">

            <div class="d-flex align-items-center mb-4">

                <div class="status-icon success me-3">

                    <i class="bi bi-code-slash"></i>

                </div>

                <div>

                    <h5 class="mb-1">
                        Fullscript Response
                    </h5>

                    <small class="text-muted">
                        Complete response received from Fullscript
                    </small>

                </div>

            </div>


            <div
                class="border rounded bg-dark p-3"
                style="max-height: 700px; overflow-y: auto;"
            >

                <pre
                    class="text-light mb-0"
                    style="
                        white-space: pre-wrap;
                        word-break: break-word;
                        font-size: 13px;
                    "
                ><code>{{ json_encode(
                    $fulfillment->fullscript_response,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                ) }}</code></pre>

            </div>

        </div>

    </div>

</div>

@endsection