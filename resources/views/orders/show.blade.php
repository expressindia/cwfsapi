@extends('layouts.app')

@section('content')

<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="h3 mb-1">Order Details</h1>

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


    {{-- Error --}}
    @if(!empty($error))

        <div class="alert alert-danger">
            <strong>Error:</strong>
            {{ $error }}
        </div>

    @endif


    @if($order)

        {{-- Order Information --}}
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
                                ($order['customer']['firstName'] ?? '') . ' ' .
                                ($order['customer']['lastName'] ?? '')
                            ) ?: '-' }}
                        </strong>

                        @if(!empty($order['customer']['email']))
                            <div class="small text-muted">
                                {{ $order['customer']['email'] }}
                            </div>
                        @endif
                    </div>


                    {{-- Date --}}
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
                            {{ $order['totalPriceSet']['shopMoney']['currencyCode'] ?? '' }}
                            {{ $order['totalPriceSet']['shopMoney']['amount'] ?? '0.00' }}
                        </strong>
                    </div>


                    {{-- Financial Status --}}
                    <div class="col-md-4">
                        <small class="text-muted d-block">
                            Financial Status
                        </small>

                        <span class="badge bg-success">
                            {{ $order['displayFinancialStatus'] ?? '-' }}
                        </span>
                    </div>


                    {{-- Fulfillment Status --}}
                    <div class="col-md-4">
                        <small class="text-muted d-block">
                            Fulfillment Status
                        </small>

                        <span class="badge bg-secondary">
                            {{ $order['displayFulfillmentStatus'] ?? '-' }}
                        </span>
                    </div>

                </div>

            </div>

        </div>


        {{-- Fulfillments --}}
        <div class="card">

            <div class="card-header">
                <h5 class="mb-0">
                    Fulfillments
                </h5>
            </div>

            <div class="card-body">

                @if(!empty($order['fulfillments']))

                    @foreach($order['fulfillments'] as $fulfillment)

                        <div class="border rounded p-4 mb-3">

                            <div class="row g-4">

                                {{-- Fulfillment ID --}}
                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Fulfillment ID
                                    </small>

                                    <code class="text-break">
                                        {{ $fulfillment['id'] ?? '-' }}
                                    </code>

                                </div>


                                {{-- Status --}}
                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Status
                                    </small>

                                    <strong>
                                        {{ $fulfillment['status'] ?? '-' }}
                                    </strong>

                                </div>


                                {{-- Shipping Carrier --}}
                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Shipping Carrier
                                    </small>

                                    <strong>
                                        {{ $fulfillment['trackingInfo']['company'] ?? 'No carrier' }}
                                    </strong>

                                </div>


                                {{-- Tracking Number --}}
                                <div class="col-md-6">

                                    <small class="text-muted d-block">
                                        Tracking Number
                                    </small>

                                    <strong>
                                        {{ $fulfillment['trackingInfo']['number'] ?? 'No tracking number' }}
                                    </strong>

                                </div>


                                {{-- Tracking URL --}}
                                @if(!empty($fulfillment['trackingInfo']['url']))

                                    <div class="col-12">

                                        <small class="text-muted d-block">
                                            Tracking URL
                                        </small>

                                        <a href="{{ $fulfillment['trackingInfo']['url'] }}"
                                           target="_blank"
                                           rel="noopener noreferrer">

                                            Track Shipment
                                        </a>

                                    </div>

                                @endif

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