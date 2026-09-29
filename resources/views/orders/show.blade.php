@extends('layouts.app')

@section('content')

<div class="container-fluid">

    {{-- ============================================================= --}}
    {{-- PAGE HEADER --}}
    {{-- ============================================================= --}}

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Order {{ $order['name'] ?? '' }}
            </h2>

            <div class="text-muted">
                FSWarehouse Order
            </div>

        </div>


        <a
            href="{{ route('orders.index') }}"
            class="btn btn-outline-secondary"
        >
            <i class="bi bi-arrow-left"></i>
            Back to Orders
        </a>

    </div>


    {{-- ============================================================= --}}
    {{-- ERROR MESSAGE --}}
    {{-- ============================================================= --}}

    @if(session('error'))

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="bi bi-exclamation-triangle me-1"></i>

            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- SUCCESS MESSAGE --}}
    {{-- ============================================================= --}}

    @if(session('success'))

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle me-1"></i>

            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- PAGE ERROR --}}
    {{-- ============================================================= --}}

    @if(isset($error) && $error)

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle me-1"></i>

            {{ $error }}

        </div>

    @endif


    @if($order)


        {{-- ========================================================= --}}
        {{-- ORDER INFORMATION --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <h5 class="mb-0">
                    Order Information
                </h5>

            </div>


            <div class="card-body">

                <div class="row">

                    {{-- Order --}}
                    <div class="col-md-3 mb-3">

                        <div class="text-muted small">
                            Order
                        </div>

                        <strong>
                            {{ $order['name'] ?? '-' }}
                        </strong>

                    </div>


                    {{-- Date --}}
                    <div class="col-md-3 mb-3">

                        <div class="text-muted small">
                            Date
                        </div>

                        <strong>
                            {{ $order['createdAt'] ?? '-' }}
                        </strong>

                    </div>


                    {{-- Financial Status --}}
                    <div class="col-md-3 mb-3">

                        <div class="text-muted small">
                            Financial Status
                        </div>

                        <span class="badge bg-success">

                            {{ $order['displayFinancialStatus'] ?? '-' }}

                        </span>

                    </div>


                    {{-- Fulfillment Status --}}
                    <div class="col-md-3 mb-3">

                        <div class="text-muted small">
                            Fulfillment Status
                        </div>

                        <span class="badge bg-secondary">

                            {{ $order['displayFulfillmentStatus'] ?? '-' }}

                        </span>

                    </div>

                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- CUSTOMER --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <h5 class="mb-0">
                    Customer
                </h5>

            </div>


            <div class="card-body">

                @php

                    $customerName = trim(
                        ($order['customer']['firstName'] ?? '') .
                        ' ' .
                        ($order['customer']['lastName'] ?? '')
                    );

                @endphp


                <div class="row">

                    <div class="col-md-4">

                        <div class="text-muted small">
                            Name
                        </div>

                        <strong>
                            {{ $customerName ?: '-' }}
                        </strong>

                    </div>


                    <div class="col-md-4">

                        <div class="text-muted small">
                            Email
                        </div>

                        <strong>
                            {{ $order['customer']['email'] ?? '-' }}
                        </strong>

                    </div>

                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- FSWAREHOUSE --}}
        {{-- ========================================================= --}}

        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h5 class="mb-0">
                            FSWarehouse Fulfillment
                        </h5>

                        <small class="text-muted">
                            Only products assigned to FSWarehouse are shown.
                        </small>

                    </div>


                    <span class="badge bg-primary">
                        FSWarehouse
                    </span>

                </div>

            </div>


            <div class="card-body">


                {{-- ================================================= --}}
                {{-- FULFILLMENT ORDERS --}}
                {{-- ================================================= --}}

                @foreach(
                    $order['fsFulfillmentOrders'] ?? []
                    as $fulfillmentOrder
                )

                    <div class="border rounded p-3 mb-4">


                        {{-- ================================================= --}}
                        {{-- FULFILLMENT ORDER HEADER --}}
                        {{-- ================================================= --}}

                        <div class="d-flex justify-content-between align-items-center mb-4">

                            <div>

                                <div class="text-muted small">
                                    Fulfillment Order
                                </div>

                                <strong>
                                    {{ $fulfillmentOrder['id'] ?? '-' }}
                                </strong>

                            </div>


                            <span class="badge bg-secondary">

                                {{ $fulfillmentOrder['status'] ?? '-' }}

                            </span>

                        </div>


                        {{-- ================================================= --}}
                        {{-- LOCATION --}}
                        {{-- ================================================= --}}

                        <div class="mb-4">

                            <div class="text-muted small">
                                Fulfillment Location
                            </div>

                            <strong>

                                {{
                                    $fulfillmentOrder['assignedLocation']['location']['name']
                                    ?? 'FSWarehouse'
                                }}

                            </strong>

                        </div>


                        {{-- ================================================= --}}
                        {{-- PRODUCTS --}}
                        {{-- ================================================= --}}

                        <h6 class="mb-3">
                            Products
                        </h6>


                        @forelse(
                            $fulfillmentOrder['lineItems']['nodes'] ?? []
                            as $lineItem
                        )

                            <div class="border rounded p-3 mb-2">

                                <div class="row align-items-center">


                                    {{-- Product --}}
                                    <div class="col-md-5">

                                        <div class="text-muted small">
                                            Product
                                        </div>

                                        <strong>

                                            {{
                                                $lineItem['lineItem']['name']
                                                ?? '-'
                                            }}

                                        </strong>

                                    </div>


                                    {{-- SKU --}}
                                    <div class="col-md-3">

                                        <div class="text-muted small">
                                            SKU
                                        </div>

                                        <strong>

                                            {{
                                                $lineItem['lineItem']['sku']
                                                ?? '-'
                                            }}

                                        </strong>

                                    </div>


                                    {{-- Quantity --}}
                                    <div class="col-md-2">

                                        <div class="text-muted small">
                                            Quantity
                                        </div>

                                        <strong>

                                            {{
                                                $lineItem['totalQuantity']
                                                ?? 0
                                            }}

                                        </strong>

                                    </div>


                                    {{-- Remaining --}}
                                    <div class="col-md-2">

                                        <div class="text-muted small">
                                            Remaining
                                        </div>

                                        <strong>

                                            {{
                                                $lineItem['remainingQuantity']
                                                ?? 0
                                            }}

                                        </strong>

                                    </div>

                                </div>

                            </div>

                        @empty

                            <div class="alert alert-info">

                                No FSWarehouse products found.

                            </div>

                        @endforelse


                        {{-- ================================================= --}}
                        {{-- SHIPMENT / TRACKING --}}
                        {{-- ================================================= --}}

                        <div class="mt-4">

                            <h6 class="mb-3">
                                Shipment & Tracking
                            </h6>


                            @php

                                $fulfillments =
                                    $fulfillmentOrder['fulfillments']['nodes']
                                    ?? [];

                            @endphp


                            @forelse(
                                $fulfillments
                                as $fulfillment
                            )

                                @php

                                    $trackingInfo =
                                        $fulfillment['trackingInfo']
                                        ?? [];

                                    $hasTracking =
                                        collect($trackingInfo)
                                            ->contains(
                                                function ($tracking) {
                                                    return filled(
                                                        $tracking['number']
                                                        ?? null
                                                    );
                                                }
                                            );

                                    $fulfillmentId =
                                        str_replace(
                                            'gid://shopify/Fulfillment/',
                                            '',
                                            $fulfillment['id'] ?? ''
                                        );

                                    $orderNumericId =
                                        str_replace(
                                            'gid://shopify/Order/',
                                            '',
                                            $order['id'] ?? ''
                                        );

                                    $existingCarrier =
                                        $trackingInfo[0]['company']
                                        ?? '';

                                    $existingTrackingNumber =
                                        $trackingInfo[0]['number']
                                        ?? '';

                                @endphp


                                <div class="border rounded p-3">


                                    {{-- ================================================= --}}
                                    {{-- FULFILLMENT HEADER --}}
                                    {{-- ================================================= --}}

                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                        <div>

                                            <div class="text-muted small">
                                                Fulfillment
                                            </div>

                                            <strong>
                                                {{ $fulfillment['id'] ?? '-' }}
                                            </strong>

                                        </div>


                                        <span class="badge bg-secondary">

                                            {{ $fulfillment['status'] ?? '-' }}

                                        </span>

                                    </div>


                                    {{-- ================================================= --}}
                                    {{-- TRACKING INFORMATION --}}
                                    {{-- ================================================= --}}

                                    @if($hasTracking)


                                        @foreach(
                                            $trackingInfo
                                            as $tracking
                                        )

                                            @if(
                                                filled(
                                                    $tracking['number']
                                                    ?? null
                                                )
                                            )

                                                <div class="row mb-3">


                                                    {{-- Carrier --}}
                                                    <div class="col-md-4">

                                                        <div class="text-muted small">
                                                            Carrier
                                                        </div>

                                                        <strong>

                                                            {{
                                                                $tracking['company']
                                                                ?? '-'
                                                            }}

                                                        </strong>

                                                    </div>


                                                    {{-- Tracking Number --}}
                                                    <div class="col-md-4">

                                                        <div class="text-muted small">
                                                            Tracking Number
                                                        </div>

                                                        <strong>

                                                            {{
                                                                $tracking['number']
                                                            }}

                                                        </strong>

                                                    </div>


                                                    {{-- Tracking URL --}}
                                                    <div class="col-md-4">

                                                        <div class="text-muted small mb-1">
                                                            Tracking
                                                        </div>


                                                        @if(
                                                            filled(
                                                                $tracking['url']
                                                                ?? null
                                                            )
                                                        )

                                                            <a
                                                                href="{{ $tracking['url'] }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-sm btn-outline-primary"
                                                            >

                                                                <i class="bi bi-box-arrow-up-right"></i>

                                                                Track Package

                                                            </a>

                                                        @else

                                                            <span class="text-muted">
                                                                No tracking URL
                                                            </span>

                                                        @endif

                                                    </div>

                                                </div>

                                            @endif

                                        @endforeach


                                    @else


                                        <div class="alert alert-warning">

                                            <i class="bi bi-exclamation-triangle me-1"></i>

                                            No tracking information has been added.

                                        </div>


                                    @endif


                                    {{-- ================================================= --}}
                                    {{-- ADD / UPDATE BUTTON --}}
                                    {{-- ================================================= --}}

                                    <button
                                        type="button"
                                        class="btn btn-sm
                                            {{
                                                $hasTracking
                                                    ? 'btn-outline-primary'
                                                    : 'btn-primary'
                                            }}"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#trackingForm{{ $fulfillmentId }}"
                                    >

                                        @if($hasTracking)

                                            <i class="bi bi-pencil"></i>

                                            Update Tracking Information

                                        @else

                                            <i class="bi bi-plus-circle"></i>

                                            Add Tracking Information

                                        @endif

                                    </button>


                                    {{-- ================================================= --}}
                                    {{-- TRACKING FORM --}}
                                    {{-- ================================================= --}}

                                    <div
                                        class="collapse mt-3"
                                        id="trackingForm{{ $fulfillmentId }}"
                                    >

                                        <div class="card card-body bg-light">


                                            <form
                                                method="POST"
                                                action="{{
                                                    route(
                                                        'orders.tracking.update',
                                                        [
                                                            'orderId' =>
                                                                $orderNumericId,

                                                            'fulfillmentId' =>
                                                                $fulfillmentId,
                                                        ]
                                                    )
                                                }}"
                                            >

                                                @csrf


                                                <div class="row">


                                                    {{-- Carrier --}}
                                                    <div class="col-md-5 mb-3">

                                                        <label
                                                            for="company{{ $fulfillmentId }}"
                                                            class="form-label"
                                                        >
                                                            Shipping Carrier
                                                        </label>


                                                        <select
                                                            name="company"
                                                            id="company{{ $fulfillmentId }}"
                                                            class="form-select"
                                                            required
                                                        >

                                                            <option value="">
                                                                Select carrier
                                                            </option>


                                                            @foreach(
                                                                config(
                                                                    'shopify.tracking_carriers',
                                                                    []
                                                                )
                                                                as $carrier
                                                            )

                                                                <option
                                                                    value="{{ $carrier }}"

                                                                    @selected(
                                                                        old(
                                                                            'company'
                                                                        ) === $carrier
                                                                        ||
                                                                        (
                                                                            $hasTracking
                                                                            &&
                                                                            $existingCarrier === $carrier
                                                                        )
                                                                    )
                                                                >

                                                                    {{ $carrier }}

                                                                </option>

                                                            @endforeach

                                                        </select>

                                                    </div>


                                                    {{-- Tracking Number --}}
                                                    <div class="col-md-5 mb-3">

                                                        <label
                                                            for="trackingNumber{{ $fulfillmentId }}"
                                                            class="form-label"
                                                        >
                                                            Tracking Number
                                                        </label>


                                                        <input
                                                            type="text"
                                                            name="tracking_number"
                                                            id="trackingNumber{{ $fulfillmentId }}"
                                                            class="form-control"

                                                            value="{{
                                                                old(
                                                                    'tracking_number',
                                                                    $existingTrackingNumber
                                                                )
                                                            }}"

                                                            placeholder="Enter tracking number"
                                                            required
                                                        >

                                                    </div>


                                                    {{-- Submit --}}
                                                    <div class="col-md-2 mb-3 d-flex align-items-end">

                                                        <button
                                                            type="submit"
                                                            class="btn btn-success w-100"
                                                        >

                                                            @if($hasTracking)

                                                                Update

                                                            @else

                                                                Add

                                                            @endif

                                                        </button>

                                                    </div>

                                                </div>

                                            </form>

                                        </div>

                                    </div>

                                </div>


                            @empty


                                {{-- ================================================= --}}
                                {{-- NO FULFILLMENT YET --}}
                                {{-- ================================================= --}}

                                <div class="alert alert-info">

                                    <i class="bi bi-info-circle me-1"></i>

                                    <strong>
                                        No shipment has been created yet.
                                    </strong>

                                    <br>

                                    <small>

                                        The FSWarehouse fulfillment order
                                        exists, but a Shopify fulfillment
                                        shipment has not been created yet.

                                        Tracking information can be added
                                        after the fulfillment is created.

                                    </small>

                                </div>


                            @endforelse

                        </div>

                    </div>

                @endforeach

            </div>

        </div>


    @else


        {{-- ========================================================= --}}
        {{-- ORDER NOT FOUND --}}
        {{-- ========================================================= --}}

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle me-1"></i>

            Order could not be loaded.

        </div>


    @endif

</div>

@endsection