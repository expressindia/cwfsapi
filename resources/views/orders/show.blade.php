@extends('layouts.app')

@section('content')

<div class="container-fluid">

    {{-- ============================================================= --}}
    {{-- PAGE HEADER --}}
    {{-- ============================================================= --}}

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="mb-1">
                Orders
            </h2>

            <p class="text-muted mb-0">
                Orders assigned to FSWarehouse
            </p>

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- ERROR --}}
    {{-- ============================================================= --}}

    @if($error)

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle me-1"></i>

            {{ $error }}

        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- ORDERS CARD --}}
    {{-- ============================================================= --}}

    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h5 class="mb-0">
                FSWarehouse Orders
            </h5>


            <span class="badge bg-primary">

                {{ count($orders) }}

            </span>

        </div>


        <div class="card-body">


            {{-- ===================================================== --}}
            {{-- ORDERS TABLE --}}
            {{-- ===================================================== --}}

            @if(count($orders) > 0)

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

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
                                    Fulfillment Status
                                </th>

                                <th>
                                    Tracking
                                </th>

                                <th class="text-end">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @foreach($orders as $order)

                                @php

                                    $numericOrderId =
                                        str_replace(
                                            'gid://shopify/Order/',
                                            '',
                                            $order['id'] ?? ''
                                        );

                                    $customerName =
                                        trim(
                                            ($order['customer']['firstName'] ?? '')
                                            . ' ' .
                                            ($order['customer']['lastName'] ?? '')
                                        );

                                @endphp


                                <tr>


                                    {{-- ================================= --}}
                                    {{-- ORDER --}}
                                    {{-- ================================= --}}

                                    <td>

                                        <strong>
                                            {{ $order['name'] ?? '-' }}
                                        </strong>

                                    </td>


                                    {{-- ================================= --}}
                                    {{-- CUSTOMER --}}
                                    {{-- ================================= --}}

                                    <td>

                                        @if($customerName)

                                            <div>
                                                {{ $customerName }}
                                            </div>

                                        @endif


                                        @if(
                                            !empty(
                                                $order['customer']['email']
                                                ?? null
                                            )
                                        )

                                            <small class="text-muted">

                                                {{
                                                    $order['customer']['email']
                                                }}

                                            </small>

                                        @endif


                                        @if(
                                            !$customerName
                                            &&
                                            empty(
                                                $order['customer']['email']
                                                ?? null
                                            )
                                        )

                                            -

                                        @endif

                                    </td>


                                    {{-- ================================= --}}
                                    {{-- DATE --}}
                                    {{-- ================================= --}}

                                    <td>

                                        @if(!empty($order['createdAt']))

                                            {{ \Carbon\Carbon::parse(
                                                $order['createdAt']
                                            )->format('M d, Y H:i') }}

                                        @else

                                            -

                                        @endif

                                    </td>


                                    {{-- ================================= --}}
                                    {{-- TOTAL --}}
                                    {{-- ================================= --}}

                                    <td>

                                        <strong>

                                            {{
                                                $order['totalPriceSet']['shopMoney']['amount']
                                                ?? '0.00'
                                            }}

                                        </strong>

                                        <small class="text-muted">

                                            {{
                                                $order['totalPriceSet']['shopMoney']['currencyCode']
                                                ?? ''
                                            }}

                                        </small>

                                    </td>


                                    {{-- ================================= --}}
                                    {{-- FINANCIAL STATUS --}}
                                    {{-- ================================= --}}

                                    <td>

                                        @php

                                            $financialStatus =
                                                $order[
                                                    'displayFinancialStatus'
                                                ]
                                                ?? null;

                                            $financialBadge = match(
                                                $financialStatus
                                            ) {
                                                'PAID' => 'bg-success',
                                                'PARTIALLY_PAID' => 'bg-warning text-dark',
                                                'REFUNDED' => 'bg-danger',
                                                'PARTIALLY_REFUNDED' => 'bg-warning text-dark',
                                                'PENDING' => 'bg-warning text-dark',
                                                default => 'bg-secondary',
                                            };

                                        @endphp


                                        <span
                                            class="badge {{ $financialBadge }}"
                                        >

                                            {{
                                                $financialStatus
                                                ?? '-'
                                            }}

                                        </span>

                                    </td>


                                    {{-- ================================= --}}
                                    {{-- FULFILLMENT STATUS --}}
                                    {{-- ================================= --}}

                                    <td>

                                        @php

                                            $fulfillmentStatus =
                                                $order[
                                                    'displayFulfillmentStatus'
                                                ]
                                                ?? null;

                                            $fulfillmentBadge = match(
                                                $fulfillmentStatus
                                            ) {
                                                'FULFILLED' => 'bg-success',
                                                'PARTIALLY_FULFILLED' => 'bg-warning text-dark',
                                                default => 'bg-secondary',
                                            };

                                        @endphp


                                        <span
                                            class="badge {{ $fulfillmentBadge }}"
                                        >

                                            {{
                                                $fulfillmentStatus
                                                ?? '-'
                                            }}

                                        </span>

                                    </td>


                                    {{-- ================================= --}}
                                    {{-- TRACKING --}}
                                    {{-- ================================= --}}

                                    <td>

                                        <span class="text-muted">
                                            —
                                        </span>

                                    </td>


                                    {{-- ================================= --}}
                                    {{-- VIEW --}}
                                    {{-- ================================= --}}

                                    <td class="text-end">

                                        <a
                                            href="{{
                                                route(
                                                    'orders.show',
                                                    [
                                                        'orderId' =>
                                                            $numericOrderId
                                                    ]
                                                )
                                            }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >

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


                {{-- ================================================= --}}
                {{-- EMPTY STATE --}}
                {{-- ================================================= --}}

                <div class="text-center py-5">

                    <i
                        class="bi bi-inbox fs-1 text-muted"
                    ></i>


                    <h5 class="mt-3">

                        No FSWarehouse orders found

                    </h5>


                    <p class="text-muted mb-0">

                        There are currently no orders assigned
                        to FSWarehouse.

                    </p>

                </div>


            @endif


            {{-- ===================================================== --}}
            {{-- PAGINATION --}}
            {{-- ===================================================== --}}

            @if(
                ($previousUrl ?? null)
                ||
                ($nextUrl ?? null)
            )

                <div
                    class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top"
                >


                    {{-- ============================================= --}}
                    {{-- PAGINATION INFO --}}
                    {{-- ============================================= --}}

                    <div class="text-muted small">

                        Showing FSWarehouse orders

                    </div>


                    {{-- ============================================= --}}
                    {{-- PAGINATION BUTTONS --}}
                    {{-- ============================================= --}}

                    <div class="d-flex gap-2">


                        {{-- Previous --}}
                        @if($previousUrl)

                            <a
                                href="{{ $previousUrl }}"
                                class="btn btn-outline-secondary"
                            >

                                <i class="bi bi-arrow-left"></i>

                                Previous

                            </a>

                        @endif


                        {{-- Next --}}
                        @if($nextUrl)

                            <a
                                href="{{ $nextUrl }}"
                                class="btn btn-outline-primary"
                            >

                                Next

                                <i class="bi bi-arrow-right"></i>

                            </a>

                        @endif

                    </div>

                </div>

            @endif


        </div>

    </div>

</div>

@endsection