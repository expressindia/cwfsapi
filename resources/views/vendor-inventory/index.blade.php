@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Vendor Inventory Move</h1>

            <p class="text-muted mb-0">
                Move a vendor's available inventory to FSWarehouse.
            </p>
        </div>
    </div>

    {{-- Error --}}
    @if(session('error'))
        <div class="alert alert-danger">
            <strong>Error:</strong>
            {{ session('error') }}
        </div>
    @endif

    {{-- Success --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>

            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Vendor Search --}}
    <div class="card mb-4">
        <div class="card-header">
            <strong>Vendor</strong>
        </div>

        <div class="card-body">
            <form
                method="POST"
                action="{{ url('/vendor-inventory/preview') }}"
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
                            Example: Designs for Health
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

    @if(!empty($preview))

        {{-- Preview Summary --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>
                    Inventory Preview
                </strong>
            </div>

            <div class="card-body">

                <div class="row g-4">

                    {{-- Vendor --}}
                    <div class="col-md-3">
                        <strong>Vendor</strong>

                        <div>
                            {{ $preview['vendor'] ?? '-' }}
                        </div>
                    </div>

                    {{-- Destination --}}
                    <div class="col-md-3">
                        <strong>Destination</strong>

                        <div>
                            {{ $preview['fs_warehouse']['name'] ?? 'FSWarehouse' }}
                        </div>
                    </div>

                    {{-- Products --}}
                    <div class="col-md-3">
                        <strong>Products</strong>

                        <div>
                            {{ number_format($preview['product_count'] ?? 0) }}
                        </div>
                    </div>

                    {{-- Variants --}}
                    <div class="col-md-3">
                        <strong>Variants</strong>

                        <div>
                            {{ number_format($preview['variant_count'] ?? 0) }}
                        </div>
                    </div>

                </div>

                <hr>

                <div class="row g-4">

                    {{-- Total Inventory --}}
                    <div class="col-md-4">
                        <strong>Total Inventory</strong>

                        <div class="fs-5">
                            {{ number_format($preview['total_inventory'] ?? 0) }}
                        </div>
                    </div>

                    {{-- Current FSWarehouse --}}
                    <div class="col-md-4">
                        <strong>Current FSWarehouse</strong>

                        <div class="fs-5">
                            {{ number_format($preview['fswarehouse_current_inventory'] ?? 0) }}
                        </div>
                    </div>

                    {{-- Inventory to Move --}}
                    <div class="col-md-4">
                        <strong>Inventory to Move</strong>

                        <div class="fs-5">
                            {{ number_format($preview['inventory_to_move'] ?? 0) }}
                        </div>
                    </div>

                </div>

            </div>
        </div>

        {{-- Inventory Table --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>
                    Inventory Details
                </strong>
            </div>

            <div class="card-body p-0">

                @if(!empty($preview['variants']))

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 align-middle">

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
                                        Other Locations
                                    </th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach($preview['variants'] as $row)

                                    <tr>

                                        {{-- Product --}}
                                        <td>
                                            {{ $row['product_title'] ?? '-' }}
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
                                            {{ number_format($row['fs_quantity'] ?? 0) }}
                                        </td>

                                        {{-- Other Quantity --}}
                                        <td class="text-end">
                                            {{ number_format($row['other_quantity'] ?? 0) }}
                                        </td>

                                        {{-- Total --}}
                                        <td class="text-end">
                                            <strong>
                                                {{ number_format($row['total_quantity'] ?? 0) }}
                                            </strong>
                                        </td>

                                        {{-- New FSWarehouse --}}
                                        <td class="text-end">
                                            <strong>
                                                {{ number_format($row['new_fs_quantity'] ?? 0) }}
                                            </strong>
                                        </td>

                                        {{-- Other Locations --}}
                                        <td>

                                            @forelse(($row['other_locations'] ?? []) as $location)

                                                <div class="mb-1">
                                                    <span>
                                                        {{ $location['location_name'] ?? 'Unknown' }}
                                                    </span>

                                                    <strong class="float-end">
                                                        {{ number_format($location['quantity'] ?? 0) }}
                                                    </strong>
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

        {{-- Move Inventory --}}
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
                            Move all available inventory to
                            <strong>
                                {{ $preview['fs_warehouse']['name'] ?? 'FSWarehouse' }}
                            </strong>.
                        </li>

                        <li>
                            Set FSWarehouse inventory to the total
                            available quantity.
                        </li>

                        <li>
                            Deactivate all other inventory locations
                            for these variants.
                        </li>

                        <li>
                            FSWarehouse will become the only active
                            inventory location for these variants.
                        </li>
                    </ul>

                    <div class="alert alert-warning">
                        <strong>Important:</strong>
                        Verify the inventory quantities above before
                        clicking Move Inventory.
                    </div>

                    <form
                        method="POST"
                        action="{{ url('/vendor-inventory/move') }}"
                        onsubmit="return confirm(
                            'Are you sure you want to move all inventory for this vendor to FSWarehouse?'
                        );"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="vendor"
                            value="{{ $preview['vendor'] ?? ($vendor ?? '') }}"
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

    {{-- Move Result --}}
    @if(!empty($moveResult))

        <div class="card mb-4">

            <div class="card-header">
                <strong>
                    Move Result
                </strong>
            </div>

            <div class="card-body">

                <div class="row mb-3">

                    <div class="col-md-4">
                        <strong>Total</strong>

                        <div>
                            {{ number_format($moveResult['summary']['total'] ?? 0) }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <strong>Successful</strong>

                        <div class="text-success">
                            {{ number_format($moveResult['summary']['successful'] ?? 0) }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <strong>Failed</strong>

                        <div class="text-danger">
                            {{ number_format($moveResult['summary']['failed'] ?? 0) }}
                        </div>
                    </div>

                </div>

                @if(!empty($moveResult['results']))

                    <div class="table-responsive">

                        <table class="table table-bordered">

                            <thead class="table-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Old Total</th>
                                    <th>Old FSWarehouse</th>
                                    <th>New FSWarehouse</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach($moveResult['results'] as $result)

                                    <tr>

                                        <td>
                                            <code>
                                                {{ $result['sku'] ?? '-' }}
                                            </code>
                                        </td>

                                        <td>
                                            {{ number_format($result['old_total_quantity'] ?? 0) }}
                                        </td>

                                        <td>
                                            {{ number_format($result['old_fs_quantity'] ?? 0) }}
                                        </td>

                                        <td>
                                            {{ number_format($result['new_fs_quantity'] ?? 0) }}
                                        </td>

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