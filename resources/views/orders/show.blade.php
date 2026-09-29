@extends('layouts.app')

@section('title', 'Order ' . ($order['name'] ?? ''))

@section('content')

<div class="container-fluid">

    {{-- ============================================================
         PAGE HEADER
    ============================================================= --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="h3 mb-1">
                Order {{ $order['name'] ?? '' }}
            </h1>

            <p class="text-muted mb-0">
                FSWarehouse fulfillment details
            </p>
        </div>

        <div>
            <a href="{{ route('orders.index') }}"
               class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left me-1"></i>

                Back to Orders

            </a>
        </div>

    </div>


    {{-- ============================================================
         SUCCESS MESSAGE
    ============================================================= --}}
    @if(session('success'))

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle me-1"></i>

            {{ session('success') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    @endif


    {{-- ============================================================
         ERROR MESSAGE
    ============================================================= --}}
    @if(!empty($error))

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="bi bi-exclamation-triangle me-1"></i>

            {{ $error }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    @endif


    {{-- ============================================================
         SESSION ERROR
    ============================================================= --}}
    @if(session('error'))

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="bi bi-exclamation-triangle me-1"></i>

            {{ session('error') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    @endif


    {{-- ============================================================
         ORDER NOT FOUND
    ============================================================= --}}
    @if(empty($order))

        <div class="card">

            <div class="card-body text-center py-5">

                <i class="bi bi-search display-5 text-muted"></i>

                <h5 class="mt-3">
                    Order not found
                </h5>

                <p class="text-muted">
                    The order could not be found in FSWarehouse.
                </p>

                <a href="{{ route('orders.index') }}"
                   class="btn btn-primary">

                    Back to Orders

                </a>

            </div>

        </div>

        @return

    @endif


    {{-- ============================================================
         ORDER INFORMATION
    ============================================================= --}}
    <div class="row g-4">

        <div class="col-lg-8">

            {{-- ----------------------------------------------------
                 ORDER DETAILS
            ----------------------------------------------------- --}}
            <div class="card mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Order Information
                    </h5>

                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Order
                            </div>

                            <div class="fw-semibold">
                                {{ $order['name'] ?? '—' }}
                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Shopify Order ID
                            </div>

                            <div class="fw-semibold text-break">
                                {{ $order['id'] ?? '—' }}
                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Created
                            </div>

                            <div>
                                @if(!empty($order['createdAt']))
                                    {{ \Carbon\Carbon::parse($order['createdAt'])->format('M d, Y h:i A') }}
                                @else
                                    —
                                @endif
                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Total
                            </div>

                            <div class="fw-semibold">

                                {{ $order['totalPriceSet']['shopMoney']['currencyCode'] ?? '' }}

                                {{ $order['totalPriceSet']['shopMoney']['amount'] ?? '0.00' }}

                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Financial Status
                            </div>

                            <div>
                                {{ $order['displayFinancialStatus'] ?? '—' }}
                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Fulfillment Status
                            </div>

                            <div>
                                {{ $order['displayFulfillmentStatus'] ?? '—' }}
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            {{-- ----------------------------------------------------
                 CUSTOMER
            ----------------------------------------------------- --}}
            <div class="card mb-4">

                <div class="card-header">

                    <h5 class="mb-0">
                        Customer
                    </h5>

                </div>

                <div class="card-body">

                    @php
                        $customer = $order['customer'] ?? null;

                        $customerName = trim(
                            ($customer['firstName'] ?? '') .
                            ' ' .
                            ($customer['lastName'] ?? '')
                        );
                    @endphp

                    @if($customer)

                        <div class="row">

                            <div class="col-md-6">

                                <div class="text-muted small">
                                    Name
                                </div>

                                <div class="fw-semibold">
                                    {{ $customerName ?: '—' }}
                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small">
                                    Email
                                </div>

                                <div>
                                    {{ $customer['email'] ?? '—' }}
                                </div>

                            </div>

                        </div>

                    @else

                        <span class="text-muted">
                            No customer information available.
                        </span>

                    @endif

                </div>

            </div>


            {{-- ====================================================
                 FSWarehouse FULFILLMENT
            ===================================================== --}}
            <div class="card mb-4">

                <div class="card-header">

                    <div class="d-flex justify-content-between align-items-center">

                        <h5 class="mb-0">
                            FSWarehouse Fulfillment
                        </h5>

                        <span class="badge bg-primary">
                            FSWarehouse
                        </span>

                    </div>

                </div>


                <div class="card-body">

                    @if(!empty($order['fsFulfillmentOrders']))

                        @foreach($order['fsFulfillmentOrders'] as $fulfillmentOrder)

                            {{-- =================================================
                                 FULFILLMENT ORDER INFORMATION
                            ================================================== --}}
                            <div class="border rounded p-3 mb-4">

                                <div class="row">

                                    <div class="col-md-6 mb-3">

                                        <div class="text-muted small">
                                            Fulfillment Order ID
                                        </div>

                                        <div class="fw-semibold text-break">
                                            {{ $fulfillmentOrder['id'] ?? '—' }}
                                        </div>

                                    </div>


                                    <div class="col-md-3 mb-3">

                                        <div class="text-muted small">
                                            Status
                                        </div>

                                        <div>
                                            {{ $fulfillmentOrder['status'] ?? '—' }}
                                        </div>

                                    </div>


                                    <div class="col-md-3 mb-3">

                                        <div class="text-muted small">
                                            Request Status
                                        </div>

                                        <div>
                                            {{ $fulfillmentOrder['requestStatus'] ?? '—' }}
                                        </div>

                                    </div>


                                    <div class="col-md-6">

                                        <div class="text-muted small">
                                            Assigned Location
                                        </div>

                                        <div class="fw-semibold">

                                            {{
                                                $fulfillmentOrder['assignedLocation']['location']['name']
                                                ?? 'FSWarehouse'
                                            }}

                                        </div>

                                    </div>

                                </div>


                                {{-- =================================================
                                     LINE ITEMS
                                ================================================== --}}
                                <div class="mt-4">

                                    <h6 class="mb-3">
                                        Products
                                    </h6>

                                    @if(!empty($fulfillmentOrder['lineItems']['nodes']))

                                        <div class="table-responsive">

                                            <table class="table table-sm align-middle">

                                                <thead>

                                                    <tr>

                                                        <th>
                                                            Product
                                                        </th>

                                                        <th>
                                                            SKU
                                                        </th>

                                                        <th class="text-center">
                                                            Quantity
                                                        </th>

                                                        <th class="text-center">
                                                            Remaining
                                                        </th>

                                                    </tr>

                                                </thead>

                                                <tbody>

                                                    @foreach($fulfillmentOrder['lineItems']['nodes'] as $lineItem)

                                                        @php
                                                            $product = $lineItem['lineItem'] ?? [];
                                                        @endphp

                                                        <tr>

                                                            <td>
                                                                {{ $product['name'] ?? '—' }}
                                                            </td>

                                                            <td>
                                                                {{ $product['sku'] ?? '—' }}
                                                            </td>

                                                            <td class="text-center">

                                                                {{ $lineItem['totalQuantity'] ?? 0 }}

                                                            </td>

                                                            <td class="text-center">

                                                                {{ $lineItem['remainingQuantity'] ?? 0 }}

                                                            </td>

                                                        </tr>

                                                    @endforeach

                                                </tbody>

                                            </table>

                                        </div>

                                    @else

                                        <div class="text-muted">
                                            No products found for FSWarehouse.
                                        </div>

                                    @endif

                                </div>


                                {{-- =================================================
                                     SHIPMENTS / FULFILLMENTS
                                ================================================== --}}
                                <div class="mt-4">

                                    <h6 class="mb-3">
                                        Shipment / Tracking
                                    </h6>


                                    @if(!empty($fulfillmentOrder['fulfillments']['nodes']))

                                        @foreach($fulfillmentOrder['fulfillments']['nodes'] as $fulfillment)

                                            @php
                                                $tracking = $fulfillment['trackingInfo'] ?? [];

                                                $trackingNumber =
                                                    $tracking['number'] ?? null;

                                                $trackingCompany =
                                                    $tracking['company'] ?? null;

                                                $trackingUrl =
                                                    $tracking['url'] ?? null;

                                                $fulfillmentGid =
                                                    $fulfillment['id'] ?? '';

                                                $fulfillmentNumericId =
                                                    str_replace(
                                                        'gid://shopify/Fulfillment/',
                                                        '',
                                                        $fulfillmentGid
                                                    );
                                            @endphp


                                            <div class="border rounded p-3 mb-3">

                                                <div class="row">

                                                    <div class="col-md-6 mb-3">

                                                        <div class="text-muted small">
                                                            Fulfillment ID
                                                        </div>

                                                        <div class="text-break small">
                                                            {{ $fulfillmentGid }}
                                                        </div>

                                                    </div>


                                                    <div class="col-md-6 mb-3">

                                                        <div class="text-muted small">
                                                            Status
                                                        </div>

                                                        <div>
                                                            {{ $fulfillment['status'] ?? '—' }}
                                                        </div>

                                                    </div>

                                                </div>


                                                {{-- ---------------------------------
                                                     TRACKING EXISTS
                                                ---------------------------------- --}}
                                                @if($trackingNumber)

                                                    <div class="alert alert-success mb-3">

                                                        <div class="fw-semibold mb-2">

                                                            <i class="bi bi-truck me-1"></i>

                                                            Tracking Information

                                                        </div>


                                                        <div class="row">

                                                            <div class="col-md-4">

                                                                <div class="text-muted small">
                                                                    Carrier
                                                                </div>

                                                                <div class="fw-semibold">
                                                                    {{ $trackingCompany ?: '—' }}
                                                                </div>

                                                            </div>


                                                            <div class="col-md-4">

                                                                <div class="text-muted small">
                                                                    Tracking Number
                                                                </div>

                                                                <div class="fw-semibold">
                                                                    {{ $trackingNumber }}
                                                                </div>

                                                            </div>


                                                            <div class="col-md-4">

                                                                <div class="text-muted small">
                                                                    Tracking URL
                                                                </div>

                                                                <div>

                                                                    @if($trackingUrl)

                                                                        <a href="{{ $trackingUrl }}"
                                                                           target="_blank"
                                                                           rel="noopener noreferrer">

                                                                            Track Shipment
                                                                            <i class="bi bi-box-arrow-up-right ms-1"></i>

                                                                        </a>

                                                                    @else

                                                                        <span class="text-muted">
                                                                            No URL
                                                                        </span>

                                                                    @endif

                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>


                                                    {{-- ---------------------------------
                                                         UPDATE TRACKING
                                                    ---------------------------------- --}}
                                                    <button type="button"
                                                            class="btn btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#trackingModal{{ $fulfillmentNumericId }}">

                                                        <i class="bi bi-pencil me-1"></i>

                                                        Update Tracking Information

                                                    </button>

                                                @else

                                                    {{-- ---------------------------------
                                                         NO TRACKING
                                                    ---------------------------------- --}}
                                                    <div class="alert alert-warning">

                                                        <i class="bi bi-exclamation-triangle me-1"></i>

                                                        A shipment has been created, but
                                                        tracking information has not been
                                                        added yet.

                                                    </div>


                                                    <button type="button"
                                                            class="btn btn-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#trackingModal{{ $fulfillmentNumericId }}">

                                                        <i class="bi bi-plus-circle me-1"></i>

                                                        Add Tracking Information

                                                    </button>

                                                @endif

                                            </div>


                                            {{-- =================================================
                                                 TRACKING MODAL
                                            ================================================== --}}
                                            <div class="modal fade"
                                                 id="trackingModal{{ $fulfillmentNumericId }}"
                                                 tabindex="-1"
                                                 aria-hidden="true">

                                                <div class="modal-dialog">

                                                    <div class="modal-content">

                                                        <form method="POST"
                                                              action="{{ route('orders.tracking.update', [
                                                                  'orderId' => str_replace(
                                                                      'gid://shopify/Order/',
                                                                      '',
                                                                      $order['id']
                                                                  ),
                                                                  'fulfillmentId' => $fulfillmentNumericId,
                                                              ]) }}">

                                                            @csrf


                                                            <div class="modal-header">

                                                                <h5 class="modal-title">

                                                                    {{ $trackingNumber
                                                                        ? 'Update Tracking Information'
                                                                        : 'Add Tracking Information'
                                                                    }}

                                                                </h5>

                                                                <button type="button"
                                                                        class="btn-close"
                                                                        data-bs-dismiss="modal">
                                                                </button>

                                                            </div>


                                                            <div class="modal-body">

                                                                {{-- Tracking Number --}}
                                                                <div class="mb-3">

                                                                    <label class="form-label">
                                                                        Tracking Number
                                                                    </label>

                                                                    <input type="text"
                                                                           name="tracking_number"
                                                                           class="form-control"
                                                                           value="{{ old('tracking_number', $trackingNumber) }}"
                                                                           required>

                                                                </div>


                                                                {{-- Carrier --}}
                                                                <div class="mb-3">

                                                                    <label class="form-label">
                                                                        Shipping Carrier
                                                                    </label>

                                                                    <select name="carrier"
                                                                            class="form-select"
                                                                            required>

                                                                        <option value="">
                                                                            Select Carrier
                                                                        </option>

                                                                        @foreach(config('shopify.tracking_carriers', []) as $carrier)

                                                                            <option value="{{ $carrier }}"
                                                                                @selected(
                                                                                    old(
                                                                                        'carrier',
                                                                                        $trackingCompany
                                                                                    ) === $carrier
                                                                                )>

                                                                                {{ $carrier }}

                                                                            </option>

                                                                        @endforeach

                                                                    </select>

                                                                </div>


                                                                {{-- Tracking URL --}}
                                                                <div class="mb-3">

                                                                    <label class="form-label">
                                                                        Tracking URL
                                                                        <span class="text-muted">
                                                                            (Optional)
                                                                        </span>
                                                                    </label>

                                                                    <input type="url"
                                                                           name="tracking_url"
                                                                           class="form-control"
                                                                           value="{{ old('tracking_url', $trackingUrl) }}"
                                                                           placeholder="https://...">

                                                                </div>


                                                                <div class="alert alert-info mb-0">

                                                                    <i class="bi bi-info-circle me-1"></i>

                                                                    Shopify will update the tracking
                                                                    information for this FSWarehouse
                                                                    fulfillment.

                                                                </div>

                                                            </div>


                                                            <div class="modal-footer">

                                                                <button type="button"
                                                                        class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">

                                                                    Cancel

                                                                </button>


                                                                <button type="submit"
                                                                        class="btn btn-primary">

                                                                    <i class="bi bi-check-lg me-1"></i>

                                                                    Save Tracking

                                                                </button>

                                                            </div>

                                                        </form>

                                                    </div>

                                                </div>

                                            </div>

                                        @endforeach

                                    @else

                                        {{-- =================================================
                                             NO ACTUAL FULFILLMENT
                                        ================================================== --}}
                                        <div class="alert alert-info mb-0">

                                            <i class="bi bi-info-circle me-1"></i>

                                            No shipment has been created yet for this
                                            FSWarehouse fulfillment order.

                                        </div>

                                    @endif

                                </div>

                            </div>

                        @endforeach

                    @else

                        <div class="alert alert-info mb-0">

                            <i class="bi bi-info-circle me-1"></i>

                            No FSWarehouse fulfillment information was found.

                        </div>

                    @endif

                </div>

            </div>

        </div>


        {{-- ============================================================
             RIGHT SIDEBAR
        ============================================================= --}}
        <div class="col-lg-4">

            <div class="card">

                <div class="card-header">

                    <h5 class="mb-0">
                        FSWarehouse
                    </h5>

                </div>

                <div class="card-body">

                    <div class="d-flex align-items-center mb-3">

                        <span class="badge bg-success me-2">
                            Connected
                        </span>

                        <span>
                            Fulfillment Service
                        </span>

                    </div>


                    <p class="text-muted small mb-0">

                        Only products and fulfillment information assigned
                        to the FSWarehouse location are displayed on this page.

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection