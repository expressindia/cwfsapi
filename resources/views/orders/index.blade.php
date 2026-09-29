@extends('layouts.app')

@section('content')

<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="h3 mb-1">
                Orders
            </h1>

            <p class="text-muted mb-0">
                Orders assigned to FSWarehouse
            </p>
        </div>

    </div>


    {{-- Error --}}
    @if(!empty($error))

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle"></i>

            <strong>Error:</strong>

            {{ $error }}

        </div>

    @endif


    {{-- Orders --}}
    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h5 class="mb-0">
                FSWarehouse Orders
            </h5>

            <span class="badge bg-primary">
                {{ count($orders ?? []) }} Orders
            </span>

        </div>


        <div class="card-body p-0">

            @if(!empty($orders))

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

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

                                    $orderNumericId = str_replace(
                                        'gid://shopify/Order/',
                                        '',
                                        $order['id'] ?? ''
                                    );

                                    $customerName = trim(
                                        ($order['customer']['firstName'] ?? '') .
                                        ' ' .
                                        ($order['customer']['lastName'] ?? '')
                                    );

                                    $trackingInfo = [];

                                    foreach (
                                        $order['fulfillments'] ?? []
                                        as $fulfillment
                                    ) {
                                        foreach (
                                            $fulfillment['trackingInfo'] ?? []
                                            as $tracking
                                        ) {
                                            $trackingInfo[] = $tracking;
                                        }
                                    }

                                @endphp


                                <tr>

                                    {{-- Order --}}
                                    <td>

                                        <a
                                            href="{{ route(
                                                'orders.show',
                                                [
                                                    'orderId' => $orderNumericId
                                                ]
                                            ) }}"
                                            class="fw-semibold text-decoration-none">

                                            {{ $order['name'] ?? '-' }}

                                        </a>

                                    </td>


                                    {{-- Customer --}}
                                    <td>

                                        <div class="fw-semibold">

                                            {{
                                                $customerName ?: '-'
                                            }}

                                        </div>


                                        @if(!empty(
                                            $order['customer']['email']
                                        ))

                                            <div class="small text-muted">

                                                {{
                                                    $order['customer']['email']
                                                }}

                                            </div>

                                        @endif

                                    </td>


                                    {{-- Date --}}
                                    <td>

                                        <span class="small">

                                            {{
                                                $order['createdAt'] ?? '-'
                                            }}

                                        </span>

                                    </td>


                                    {{-- Total --}}
                                    <td>

                                        <strong>

                                            {{
                                                $order['totalPriceSet']
                                                    ['shopMoney']
                                                    ['currencyCode']
                                                ?? ''
                                            }}

                                            {{
                                                $order['totalPriceSet']
                                                    ['shopMoney']
                                                    ['amount']
                                                ?? '0.00'
                                            }}

                                        </strong>

                                    </td>


                                    {{-- Financial Status --}}
                                    <td>

                                        @php

                                            $financialStatus =
                                                $order[
                                                    'displayFinancialStatus'
                                                ] ?? '';

                                        @endphp


                                        @if($financialStatus === 'PAID')

                                            <span class="badge bg-success">

                                                {{ $financialStatus }}

                                            </span>

                                        @elseif(
                                            $financialStatus === 'REFUNDED'
                                        )

                                            <span class="badge bg-warning text-dark">

                                                {{ $financialStatus }}

                                            </span>

                                        @else

                                            <span class="badge bg-secondary">

                                                {{
                                                    $financialStatus ?: '-'
                                                }}

                                            </span>

                                        @endif

                                    </td>


                                    {{-- Fulfillment Status --}}
                                    <td>

                                        @php

                                            $fulfillmentStatus =
                                                $order[
                                                    'displayFulfillmentStatus'
                                                ] ?? '';

                                        @endphp


                                        @if(
                                            $fulfillmentStatus === 'FULFILLED'
                                        )

                                            <span class="badge bg-success">

                                                {{ $fulfillmentStatus }}

                                            </span>

                                        @elseif(
                                            $fulfillmentStatus === 'UNFULFILLED'
                                        )

                                            <span class="badge bg-warning text-dark">

                                                {{ $fulfillmentStatus }}

                                            </span>

                                        @else

                                            <span class="badge bg-secondary">

                                                {{
                                                    $fulfillmentStatus ?: '-'
                                                }}

                                            </span>

                                        @endif

                                    </td>


                                    {{-- Tracking --}}
                                    <td>

                                        @if(!empty($trackingInfo))

                                            @foreach(
                                                $trackingInfo
                                                as $tracking
                                            )

                                                <div class="small">

                                                    <strong>

                                                        {{
                                                            $tracking['company']
                                                            ?? '-'
                                                        }}

                                                    </strong>

                                                    <br>

                                                    <span class="text-muted">

                                                        {{
                                                            $tracking['number']
                                                            ?? '-'
                                                        }}

                                                    </span>

                                                </div>

                                            @endforeach

                                        @else

                                            <span class="text-muted">

                                                No tracking

                                            </span>

                                        @endif

                                    </td>


                                    {{-- Action --}}
                                    <td class="text-end">

                                        <a
                                            href="{{ route(
                                                'orders.show',
                                                [
                                                    'orderId' => $orderNumericId
                                                ]
                                            ) }}"
                                            class="btn btn-sm btn-outline-primary">

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

                    <i
                        class="bi bi-inbox fs-1 text-muted">
                    </i>

                    <h5 class="mt-3">
                        No FSWarehouse orders found
                    </h5>

                    <p class="text-muted mb-0">

                        There are currently no orders assigned
                        to your registered fulfillment service.

                    </p>

                </div>

            @endif

        </div>

    </div>

</div>

@endsection