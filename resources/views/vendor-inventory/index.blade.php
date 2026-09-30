@extends('layouts.app')

@section('content')

<div class="container-fluid py-4">

    {{-- ============================================================
         PAGE HEADER
    ============================================================ --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="h3 mb-1">
                Vendor Inventory Move
            </h1>

            <p class="text-muted mb-0">
                Move a vendor's available inventory to FSWarehouse.
            </p>
        </div>

    </div>


    {{-- ============================================================
         SUCCESS MESSAGE
    ============================================================ --}}
    @if(session('success'))

        <div class="alert alert-success">
            <strong>Success:</strong>

            {{ session('success') }}
        </div>

    @endif


    {{-- ============================================================
         ERROR MESSAGE
    ============================================================ --}}
    @if($errors->any())

        <div class="alert alert-danger">

            <strong>Error:</strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- ============================================================
         VENDOR SEARCH
    ============================================================ --}}
    <div class="card mb-4">

        <div class="card-header">
            <strong>
                Vendor
            </strong>
        </div>

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('vendor-inventory.preview') }}"
            >

                @csrf

                <div class="row align-items-end">

                    <div class="col-md-8">

                        <label
                            for="vendor"
                            class="form-label"
                        >
                            Vendor Name
                        </label>

                        <input
                            type="text"
                            name="vendor"
                            id="vendor"
                            class="form-control"
                            value="{{ old('vendor', $vendor ?? '') }}"
                            placeholder="Enter vendor name"
                            required
                        >

                        <div class="form-text">
                            Example: A.C. Grace
                        </div>

                    </div>


                    <div class="col-md-4">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Preview Inventory
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    {{-- ============================================================
         INVENTORY PREVIEW
    ============================================================ --}}
    @if(!empty($preview))

        @php

            /*
             * The service returns these values inside
             * the "summary" array.
             */
            $summary = $preview['summary'] ?? [];

            $productsCount =
                $summary['products'] ?? 0;

            $variantsCount =
                $summary['variants'] ?? 0;

            $totalInventory =
                $summary['total_inventory'] ?? 0;

            $fsWarehouseInventory =
                $summary['fswarehouse_current_inventory'] ?? 0;

            $inventoryToMove =
                $summary['inventory_to_move'] ?? 0;

            $warehouseName =
                $preview['target_location']['name']
                ?? $preview['fs_warehouse']['name']
                ?? 'FSWarehouse';

        @endphp


        {{-- ========================================================
             PREVIEW SUMMARY
        ========================================================= --}}
        <div class="card mb-4">

            <div class="card-header">

                <strong>
                    Inventory Preview
                </strong>

            </div>


            <div class="card-body">

                {{-- Basic information --}}
                <div class="row g-4">

                    {{-- Vendor --}}
                    <div class="col-md-3">

                        <strong>
                            Vendor
                        </strong>

                        <div class="mt-1">
                            {{ $preview['vendor'] ?? '-' }}
                        </div>

                    </div>


                    {{-- Destination --}}
                    <div class="col-md-3">

                        <strong>
                            Destination
                        </strong>

                        <div class="mt-1">

                            <span class="badge bg-primary">

                                {{ $warehouseName }}

                            </span>

                        </div>

                    </div>


                    {{-- Products --}}
                    <div class="col-md-3">

                        <strong>
                            Products
                        </strong>

                        <div class="fs-5 mt-1">

                            {{ number_format($productsCount) }}

                        </div>

                    </div>


                    {{-- Variants --}}
                    <div class="col-md-3">

                        <strong>
                            Variants
                        </strong>

                        <div class="fs-5 mt-1">

                            {{ number_format($variantsCount) }}

                        </div>

                    </div>

                </div>


                <hr>


                {{-- Inventory totals --}}
                <div class="row g-4">

                    {{-- Total --}}
                    <div class="col-md-4">

                        <strong>
                            Total Inventory
                        </strong>

                        <div class="fs-4 mt-1">

                            {{ number_format($totalInventory) }}

                        </div>

                        <small class="text-muted">
                            Total inventory across active locations.
                        </small>

                    </div>


                    {{-- Current FSWarehouse --}}
                    <div class="col-md-4">

                        <strong>
                            Current FSWarehouse
                        </strong>

                        <div class="fs-4 mt-1">

                            {{ number_format($fsWarehouseInventory) }}

                        </div>

                        <small class="text-muted">
                            Current inventory already at FSWarehouse.
                        </small>

                    </div>


                    {{-- Inventory to move --}}
                    <div class="col-md-4">

                        <strong>
                            Inventory to Move
                        </strong>

                        <div class="fs-4 mt-1">

                            {{ number_format($inventoryToMove) }}

                        </div>

                        <small class="text-muted">
                            Inventory that will be moved to FSWarehouse.
                        </small>

                    </div>

                </div>

            </div>

        </div>


        {{-- ========================================================
             INVENTORY DETAILS
        ========================================================= --}}
        <div class="card mb-4">

            <div class="card-header">

                <strong>
                    Inventory Details
                </strong>

            </div>


            <div class="card-body p-0">

                @if(!empty($preview['variants']))

                    <div class="table-responsive">

                        <table
                            class="table table-bordered table-hover mb-0 align-middle"
                        >

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Variant
                                    </th>

                                    <th>
                                        SKU
                                    </th>

                                    <th class="text-end">
                                        FSWarehouse
                                    </th>

                                    <th class="text-end">
                                        Other Locations
                                    </th>

                                    <th class="text-end">
                                        Total
                                    </th>

                                    <th class="text-end">
                                        New FSWarehouse
                                    </th>

                                    <th>
                                        Location Details
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @foreach($preview['variants'] as $row)

                                    @php

                                        $fsQuantity =
                                            $row['fs_quantity'] ?? 0;

                                        $totalQuantity =
                                            $row['total_quantity'] ?? 0;

                                        $newFsQuantity =
                                            $row['new_fs_quantity'] ?? $totalQuantity;

                                        /*
                                         * Calculate other active-location
                                         * inventory directly from the
                                         * locations returned by Shopify.
                                         */
                                        $otherQuantity = 0;

                                        $otherLocations = [];

                                        foreach (
                                            ($row['locations'] ?? [])
                                            as $location
                                        ) {

                                            $isFsWarehouse =
                                                (
                                                    $location['location_id']
                                                    ?? null
                                                )
                                                ===
                                                (
                                                    $preview['target_location']['id']
                                                    ?? null
                                                );

                                            if ($isFsWarehouse) {
                                                continue;
                                            }

                                            $locationQuantity =
                                                (int) (
                                                    $location['quantity']
                                                    ?? 0
                                                );

                                            $locationIsActive =
                                                (bool) (
                                                    $location['is_active']
                                                    ?? false
                                                );

                                            if ($locationIsActive) {

                                                $otherQuantity +=
                                                    $locationQuantity;

                                            }

                                            $otherLocations[] = [
                                                'location_name' =>
                                                    $location['location_name']
                                                    ?? 'Unknown',

                                                'quantity' =>
                                                    $locationQuantity,

                                                'is_active' =>
                                                    $locationIsActive,
                                            ];
                                        }

                                    @endphp


                                    <tr>

                                        {{-- Product --}}
                                        <td>

                                            <strong>
                                                {{ $row['product_title'] ?? '-' }}
                                            </strong>

                                        </td>


                                        {{-- Variant --}}
                                        <td>

                                            {{ $row['variant_title'] ?? '-' }}

                                        </td>


                                        {{-- SKU --}}
                                        <td>

                                            <code>
                                                {{ $row['sku'] ?: '-' }}
                                            </code>

                                        </td>


                                        {{-- FSWarehouse --}}
                                        <td class="text-end">

                                            <strong>
                                                {{ number_format($fsQuantity) }}
                                            </strong>

                                        </td>


                                        {{-- Other active locations --}}
                                        <td class="text-end">

                                            {{ number_format($otherQuantity) }}

                                        </td>


                                        {{-- Total --}}
                                        <td class="text-end">

                                            <strong>
                                                {{ number_format($totalQuantity) }}
                                            </strong>

                                        </td>


                                        {{-- New FSWarehouse --}}
                                        <td class="text-end">

                                            <strong class="text-success">

                                                {{ number_format($newFsQuantity) }}

                                            </strong>

                                        </td>


                                        {{-- Location details --}}
                                        <td>

                                            @forelse($otherLocations as $location)

                                                <div class="mb-2">

                                                    <div>

                                                        <span>
                                                            {{ $location['location_name'] }}
                                                        </span>

                                                        @if($location['is_active'])

                                                            <span class="badge bg-success ms-1">
                                                                Active
                                                            </span>

                                                        @else

                                                            <span class="badge bg-secondary ms-1">
                                                                Inactive
                                                            </span>

                                                        @endif

                                                    </div>

                                                    <div>

                                                        Quantity:

                                                        <strong>
                                                            {{ number_format($location['quantity']) }}
                                                        </strong>

                                                    </div>

                                                </div>

                                            @empty

                                                <span class="text-muted">
                                                    None
                                                </span>

                                            @endforelse

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <div class="p-4 text-center text-muted">

                        No inventory variants were found for this vendor.

                    </div>

                @endif

            </div>

        </div>


        {{-- ========================================================
             MOVE INVENTORY
        ========================================================= --}}
        @if(!empty($preview['variants']))

            <div class="card border-warning mb-4">

                <div class="card-header bg-warning-subtle">

                    <strong>
                        Move Inventory
                    </strong>

                </div>


                <div class="card-body">

                    <p class="mb-3">
                        This action will:
                    </p>


                    <ul>

                        <li>
                            Move available inventory to
                            <strong>
                                {{ $warehouseName }}
                            </strong>.
                        </li>

                        <li>
                            Set FSWarehouse inventory to the total
                            available quantity.
                        </li>

                        <li>
                            Deactivate all other active inventory
                            locations for these variants.
                        </li>

                        <li>
                            FSWarehouse will become the only active
                            inventory location for these variants.
                        </li>

                    </ul>


                    <div class="alert alert-warning">

                        <strong>
                            Important:
                        </strong>

                        Verify the inventory quantities above before
                        clicking Move Inventory.

                    </div>


                    {{-- Move form --}}
                    <form
                        method="POST"
                        action="{{ route('vendor-inventory.move') }}"
                        onsubmit="return confirm(
                            'Are you sure you want to move all inventory for this vendor to FSWarehouse?'
                        );"
                    >

                        @csrf


                        {{-- Vendor --}}
                        <input
                            type="hidden"
                            name="vendor"
                            value="{{ $preview['vendor'] ?? ($vendor ?? '') }}"
                        >


                        {{-- Required by Controller --}}
                        <input
                            type="hidden"
                            name="confirm"
                            value="1"
                        >


                        <button
                            type="submit"
                            class="btn btn-warning"
                        >

                            Move Inventory to FSWarehouse

                        </button>

                    </form>

                </div>

            </div>

        @endif

    @endif


    {{-- ============================================================
         MOVE RESULT
    ============================================================ --}}
    @if(!empty($moveResult))

        <div class="card mb-4">

            <div class="card-header">

                <strong>
                    Move Result
                </strong>

            </div>


            <div class="card-body">

                @php
                    $moveSummary =
                        $moveResult['summary'] ?? [];
                @endphp


                <div class="row mb-4">

                    {{-- Total --}}
                    <div class="col-md-4">

                        <strong>
                            Total
                        </strong>

                        <div class="fs-4">

                            {{ number_format(
                                $moveSummary['total'] ?? 0
                            ) }}

                        </div>

                    </div>


                    {{-- Successful --}}
                    <div class="col-md-4">

                        <strong>
                            Successful
                        </strong>

                        <div class="fs-4 text-success">

                            {{ number_format(
                                $moveSummary['successful'] ?? 0
                            ) }}

                        </div>

                    </div>


                    {{-- Failed --}}
                    <div class="col-md-4">

                        <strong>
                            Failed
                        </strong>

                        <div class="fs-4 text-danger">

                            {{ number_format(
                                $moveSummary['failed'] ?? 0
                            ) }}

                        </div>

                    </div>

                </div>


                @if(!empty($moveResult['results']))

                    <div class="table-responsive">

                        <table class="table table-bordered">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        SKU
                                    </th>

                                    <th>
                                        Old Total
                                    </th>

                                    <th>
                                        Old FSWarehouse
                                    </th>

                                    <th>
                                        New FSWarehouse
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Message
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @foreach(
                                    $moveResult['results']
                                    as $result
                                )

                                    <tr>

                                        {{-- Product --}}
                                        <td>
                                            {{ $result['product_title'] ?? '-' }}
                                        </td>


                                        {{-- SKU --}}
                                        <td>

                                            <code>
                                                {{ $result['sku'] ?? '-' }}
                                            </code>

                                        </td>


                                        {{-- Old total --}}
                                        <td>

                                            {{ number_format(
                                                $result['old_total_quantity'] ?? 0
                                            ) }}

                                        </td>


                                        {{-- Old FSWarehouse --}}
                                        <td>

                                            {{ number_format(
                                                $result['old_fs_quantity'] ?? 0
                                            ) }}

                                        </td>


                                        {{-- New FSWarehouse --}}
                                        <td>

                                            <strong>

                                                {{ number_format(
                                                    $result['new_fs_quantity'] ?? 0
                                                ) }}

                                            </strong>

                                        </td>


                                        {{-- Status --}}
                                        <td>

                                            @if($result['success'] ?? false)

                                                <span class="badge bg-success">
                                                    Success
                                                </span>

                                            @else

                                                <span class="badge bg-danger">
                                                    Failed
                                                </span>

                                            @endif

                                        </td>


                                        {{-- Message --}}
                                        <td>

                                            {{ $result['message'] ?? '' }}

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @endif

            </div>

        </div>

    @endif

</div>

@endsection