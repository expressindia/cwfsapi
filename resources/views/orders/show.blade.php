@extends('layouts.app')

@section('content')

<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="h3 mb-1">
                Order Details
            </h1>

            <p class="text-muted mb-0">
                Shopify order details
            </p>
        </div>

        <a href="{{ route('orders.index') }}"
           class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>
            Back to Orders

        </a>

    </div>


    {{-- Success Message --}}
    @if(session('success'))

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle"></i>

            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    @endif


    {{-- Error Message --}}
    @if(session('error'))

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="bi bi-exclamation-triangle"></i>

            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    @endif


    {{-- Validation Errors --}}
    @if($errors->any())

        <div class="alert alert-danger">

            <strong>
                Please correct the following:
            </strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- Controller / Shopify Error --}}
    @if(!empty($error))

        <div class="alert alert-danger">

            <strong>
                Error:
            </strong>

            {{ $error }}

        </div>

    @endif


    @if($order)

        {{-- ========================================================= --}}
        {{-- ORDER INFORMATION --}}
        {{-- ========================================================= --}}

        <div class="card mb-4">

            <div class="card-header">

                <h5 class="mb-0">
                    Shopify Order {{ $order['name'] ?? '-' }}
                </h5>

            </div>


            <div class="card-body">

                <div class="row g-4">

                    {{-- Order --}}
                    <div class="col-md-4">

                        <small class="text-muted d-block">
                            Order
                        </small>

                        <strong>
                            {{ $order['name'] ?? '-' }}
                        </strong>

                    </div>


                    {{-- Customer --}}
                    <div class="col-md-4">

                        <small class="text-muted d-block">
                            Customer
                        </small>

                        <strong>

                            {{ trim(
                                ($order['customer']['firstName'] ?? '') .
                                ' ' .
                                ($order['customer']['lastName'] ?? '')
                            ) ?: '-' }}

                        </strong>


                        @if(!empty($order['customer']['email']))

                            <div class="small text-muted">

                                {{ $order['customer']['email'] }}

                            </div>

                        @endif

                    </div>


                    {{-- Order Date --}}
                    <div class="col-md-4">

                        <small class="text-muted d-block">
                            Order Date
                        </small>

                        <strong>
                            {{ $order['createdAt'] ?? '-' }}
                        </strong>

                    </div>


                    {{-- Total --}}
                    <div class="col-md-4">

                        <small class="text-muted d-block">
                            Total
                        </small>

                        <strong>

                            {{
                                $order['totalPriceSet']['shopMoney']['currencyCode']
                                ?? ''
                            }}

                            {{
                                $order['totalPriceSet']['shopMoney']['amount']
                                ?? '0.00'
                            }}

                        </strong>

                    </div>


                    {{-- Financial Status --}}
                    <div class="col-md-4">

                        <small class="text-muted d-block">
                            Financial Status
                        </small>

                        <span class="badge bg-success">

                            {{
                                $order['displayFinancialStatus']
                                ?? '-'
                            }}

                        </span>

                    </div>


                    {{-- Fulfillment Status --}}
                    <div class="col-md-4">

                        <small class="text-muted d-block">
                            Fulfillment Status
                        </small>

                        <span class="badge bg-secondary">

                            {{
                                $order['displayFulfillmentStatus']
                                ?? '-'
                            }}

                        </span>

                    </div>

                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- FULFILLMENTS --}}
        {{-- ========================================================= --}}

        <div class="card">

            <div class="card-header">

                <h5 class="mb-0">
                    Fulfillments
                </h5>

            </div>


            <div class="card-body">

                @if(!empty($order['fulfillments']))

                    @foreach($order['fulfillments'] as $fulfillment)

                        <div class="border rounded p-4 mb-4">

                            <div class="row g-4">


                                {{-- ================================================= --}}
                                {{-- FULFILLMENT ID --}}
                                {{-- ================================================= --}}

                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Fulfillment ID
                                    </small>

                                    <code class="text-break">

                                        {{ $fulfillment['id'] ?? '-' }}

                                    </code>

                                </div>


                                {{-- ================================================= --}}
                                {{-- STATUS --}}
                                {{-- ================================================= --}}

                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Status
                                    </small>

                                    <strong>

                                        {{ $fulfillment['status'] ?? '-' }}

                                    </strong>

                                </div>


                                {{-- ================================================= --}}
                                {{-- FULFILLMENT LOCATION --}}
                                {{-- ================================================= --}}

                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Fulfillment Location
                                    </small>

                                    <strong>

                                        {{
                                            $fulfillment['location']['name']
                                            ?? '-'
                                        }}

                                    </strong>

                                    @if(!empty($fulfillment['location']['id']))

                                        <div class="small text-muted text-break">

                                            {{
                                                $fulfillment['location']['id']
                                            }}

                                        </div>

                                    @endif

                                </div>


                                {{-- ================================================= --}}
                                {{-- FULFILLMENT SERVICE --}}
                                {{-- ================================================= --}}

                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Fulfillment Service
                                    </small>

                                    <strong>

                                        {{
                                            $fulfillment['service']['serviceName']
                                            ?? '-'
                                        }}

                                    </strong>


                                    @if(!empty($fulfillment['service']['id']))

                                        <div class="small text-muted text-break">

                                            Service ID:
                                            {{
                                                $fulfillment['service']['id']
                                            }}

                                        </div>

                                    @endif


                                    @if(!empty($fulfillment['service']['handle']))

                                        <div class="small text-muted">

                                            Handle:
                                            {{
                                                $fulfillment['service']['handle']
                                            }}

                                        </div>

                                    @endif

                                </div>


                                {{-- ================================================= --}}
                                {{-- TRACKING INFORMATION --}}
                                {{-- ================================================= --}}

                                <div class="col-12">

                                    <small class="text-muted d-block mb-2">
                                        Tracking Information
                                    </small>


                                    @if(!empty($fulfillment['trackingInfo']))

                                        @foreach(
                                            $fulfillment['trackingInfo']
                                            as $tracking
                                        )

                                            <div class="border rounded p-3 mb-2">

                                                <div class="row g-3">


                                                    {{-- Carrier --}}
                                                    <div class="col-md-4">

                                                        <small class="text-muted d-block">
                                                            Shipping Carrier
                                                        </small>

                                                        <strong>

                                                            {{
                                                                $tracking['company']
                                                                ?? 'Unknown carrier'
                                                            }}

                                                        </strong>

                                                    </div>


                                                    {{-- Tracking Number --}}
                                                    <div class="col-md-4">

                                                        <small class="text-muted d-block">
                                                            Tracking Number
                                                        </small>

                                                        <strong>

                                                            {{
                                                                $tracking['number']
                                                                ?? 'No tracking number'
                                                            }}

                                                        </strong>

                                                    </div>


                                                    {{-- Tracking Link --}}
                                                    <div class="col-md-4">

                                                        <small class="text-muted d-block">
                                                            Tracking
                                                        </small>


                                                        @if(!empty($tracking['url']))

                                                            <a
                                                                href="{{ $tracking['url'] }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn btn-sm btn-outline-primary">

                                                                <i class="bi bi-box-arrow-up-right"></i>

                                                                Track

                                                            </a>

                                                        @else

                                                            <span class="text-muted">

                                                                No tracking link

                                                            </span>

                                                        @endif

                                                    </div>

                                                </div>

                                            </div>

                                        @endforeach

                                    @else

                                        <div class="text-muted">

                                            No tracking information found
                                            for this fulfillment.

                                        </div>

                                    @endif

                                </div>


                                {{-- ================================================= --}}
                                {{-- UPDATE TRACKING --}}
                                {{-- ================================================= --}}

                                <div class="col-12">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#updateTracking{{ $loop->index }}"
                                        aria-expanded="false"
                                        aria-controls="updateTracking{{ $loop->index }}">

                                        <i class="bi bi-pencil"></i>

                                        Update Tracking

                                    </button>


                                    <div
                                        class="collapse mt-3"
                                        id="updateTracking{{ $loop->index }}">

                                        <div class="border rounded p-3">

                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'orders.tracking.update',
                                                    [
                                                        'orderId' => str_replace(
                                                            'gid://shopify/Order/',
                                                            '',
                                                            $order['id']
                                                        ),

                                                        'fulfillmentId' => str_replace(
                                                            'gid://shopify/Fulfillment/',
                                                            '',
                                                            $fulfillment['id']
                                                        ),
                                                    ]
                                                ) }}">

                                                @csrf


                                                <div class="row g-3">


                                                    {{-- Carrier --}}
                                                    <div class="col-md-5">

                                                        <label
                                                            for="company{{ $loop->index }}"
                                                            class="form-label">

                                                            Shipping Carrier

                                                        </label>


                                                        <select
                                                            name="company"
                                                            id="company{{ $loop->index }}"
                                                            class="form-select"
                                                            required>

                                                            <option value="">

                                                                Select carrier

                                                            </option>


                                                            @foreach(
                                                                config(
                                                                    'shopify.tracking_carriers',
                                                                    []
                                                                ) as $carrier
                                                            )

                                                                <option
                                                                    value="{{ $carrier }}"
                                                                    @selected(
                                                                        old(
                                                                            'company',
                                                                            $fulfillment['trackingInfo'][0]['company']
                                                                            ?? ''
                                                                        ) === $carrier
                                                                    )>

                                                                    {{ $carrier }}

                                                                </option>

                                                            @endforeach

                                                        </select>

                                                    </div>


                                                    {{-- Tracking Number --}}
                                                    <div class="col-md-5">

                                                        <label
                                                            for="trackingNumber{{ $loop->index }}"
                                                            class="form-label">

                                                            Tracking Number

                                                        </label>


                                                        <input
                                                            type="text"
                                                            name="tracking_number"
                                                            id="trackingNumber{{ $loop->index }}"
                                                            class="form-control"
                                                            value="{{ old(
                                                                'tracking_number',
                                                                $fulfillment['trackingInfo'][0]['number']
                                                                ?? ''
                                                            ) }}"
                                                            placeholder="Enter tracking number"
                                                            required>

                                                    </div>


                                                    {{-- Save --}}
                                                    <div class="col-md-2 d-flex align-items-end">

                                                        <button
                                                            type="submit"
                                                            class="btn btn-primary w-100">

                                                            <i class="bi bi-save"></i>

                                                            Save

                                                        </button>

                                                    </div>

                                                </div>

                                            </form>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endforeach

                @else

                    <div class="text-muted">

                        No fulfillments found for this order.

                    </div>

                @endif

            </div>

        </div>

    @endif

</div>

@endsection