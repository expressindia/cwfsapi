@extends('layouts.app')

@section('title', 'Shopify Orders - CWFSAPI')

@section('page-title', 'Orders')

@section('content')

<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="fw-semibold mb-1">
                Shopify Orders
            </h3>

            <p class="text-muted mb-0">
                View orders and fulfillment tracking information from Shopify.
            </p>

        </div>

        <div>

            <span class="badge bg-primary px-3 py-2">

                <i class="bi bi-cart me-1"></i>

                Shopify Orders

            </span>

        </div>

    </div>


    {{-- Error --}}
    @if(!empty($error))

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle me-2"></i>

            {{ $error }}

        </div>

    @endif


    {{-- Orders Card --}}
    <div class="card dashboard-card">

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div class="d-flex align-items-center">

                    <div class="status-icon success me-3">

                        <i class="bi bi-cart"></i>

                    </div>

                    <div>

                        <h5 class="mb-1">
                            Orders
                        </h5>

                        <small class="text-muted">
                            Latest Shopify orders
                        </small>

                    </div>

                </div>

                <span class="badge bg-success px-3 py-2">

                    <i class="bi bi-cloud-check me-1"></i>

                    Shopify API

                </span>

            </div>


            @if(count($orders))

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Order
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Financial Status
                                </th>

                                <th>
                                    Fulfillment
                                </th>

                                <th>
                                    Tracking
                                </th>
                                <th>View</th>

                            </tr>

                        </thead>


                        <tbody>

                            @foreach($orders as $order)

                                <tr>

                                    {{-- Order --}}
                                    <td>

                                        <strong>
                                            {{ $order['name'] ?? '-' }}
                                        </strong>

                                    </td>


                                    {{-- Customer --}}
                                    <td>

                                        @if(!empty($order['customer']))

                                            {{ trim(
                                                ($order['customer']['firstName'] ?? '') .
                                                ' ' .
                                                ($order['customer']['lastName'] ?? '')
                                            ) }}

                                            @if(!empty($order['customer']['email']))

                                                <br>

                                                <small class="text-muted">
                                                    {{ $order['customer']['email'] }}
                                                </small>

                                            @endif

                                        @else

                                            <span class="text-muted">
                                                Guest
                                            </span>

                                        @endif

                                    </td>


                                    {{-- Date --}}
                                    <td>

                                        @if(!empty($order['createdAt']))

                                            {{ \Carbon\Carbon::parse(
                                                $order['createdAt']
                                            )->format('Y-m-d H:i') }}

                                        @else

                                            -

                                        @endif

                                    </td>


                                    {{-- Total --}}
                                    <td>

                                        @if(!empty($order['totalPriceSet']['shopMoney']))

                                            <strong>

                                                {{ $order['totalPriceSet']['shopMoney']['currencyCode'] }}

                                                {{ number_format(
                                                    (float) $order['totalPriceSet']['shopMoney']['amount'],
                                                    2
                                                ) }}

                                            </strong>

                                        @else

                                            -

                                        @endif

                                    </td>


                                    {{-- Financial Status --}}
                                    <td>

                                        @php
                                            $financialStatus =
                                                $order['displayFinancialStatus'] ?? 'UNKNOWN';
                                        @endphp

                                        <span class="badge bg-light text-dark border">

                                            {{ str_replace(
                                                '_',
                                                ' ',
                                                $financialStatus
                                            ) }}

                                        </span>

                                    </td>


                                    {{-- Fulfillment Status --}}
                                    <td>

                                        @php
                                            $fulfillmentStatus =
                                                $order['displayFulfillmentStatus'] ?? 'UNFULFILLED';
                                        @endphp

                                        @if($fulfillmentStatus === 'FULFILLED')

                                            <span class="badge bg-success">

                                                <i class="bi bi-check-circle me-1"></i>

                                                Fulfilled

                                            </span>

                                        @elseif($fulfillmentStatus === 'PARTIALLY_FULFILLED')

                                            <span class="badge bg-warning text-dark">

                                                <i class="bi bi-box-seam me-1"></i>

                                                Partially Fulfilled

                                            </span>

                                        @else

                                            <span class="badge bg-secondary">

                                                {{ str_replace(
                                                    '_',
                                                    ' ',
                                                    $fulfillmentStatus
                                                ) }}

                                            </span>

                                        @endif

                                    </td>


                                    {{-- Tracking --}}
                                    <td>

                                        @if(!empty($order['fulfillments']))

                                            @foreach($order['fulfillments'] as $fulfillment)

                                                @if(!empty($fulfillment['trackingInfo']))

                                                    @foreach($fulfillment['trackingInfo'] as $tracking)

                                                        @if(!empty($tracking['number']))

                                                            <div class="mb-2">

                                                                <strong>
                                                                    {{ $tracking['number'] }}
                                                                </strong>

                                                                @if(!empty($tracking['company']))

                                                                    <br>

                                                                    <small class="text-muted">

                                                                        <i class="bi bi-truck me-1"></i>

                                                                        {{ $tracking['company'] }}

                                                                    </small>

                                                                @endif


                                                                @if(!empty($tracking['url']))

                                                                    <br>

                                                                    <a
                                                                        href="{{ $tracking['url'] }}"
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        class="btn btn-sm btn-outline-primary mt-1"
                                                                    >

                                                                        <i class="bi bi-box-arrow-up-right me-1"></i>

                                                                        Track

                                                                    </a>

                                                                @endif

                                                            </div>

                                                        @endif

                                                    @endforeach

                                                @endif

                                            @endforeach

                                        @else

                                            <span class="text-muted">
                                                No tracking
                                            </span>

                                        @endif

                                    </td>
                                    <td>
                                        <a href="{{ route('orders.show', ['orderId' => str_replace('gid://shopify/Order/', '', $order['id'])]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                            View
                                        </a>
                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            @else

                <div class="text-center py-5">

                    <div class="status-icon success mx-auto mb-3">

                        <i class="bi bi-cart"></i>

                    </div>

                    <h5 class="mb-2">
                        No Orders Found
                    </h5>

                    <p class="text-muted mb-0">
                        No Shopify orders were returned by the API.
                    </p>

                </div>

            @endif

        </div>

    </div>

</div>

@endsection