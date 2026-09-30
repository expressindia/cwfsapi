@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Vendor Inventory Move</h1>

            <p class="text-muted mb-0">
                Move a vendor's inventory to FSWarehouse and make
                FSWarehouse the only active inventory location.
            </p>
        </div>
    </div>

    @if(session('result'))
        @php
            $result = session('result');
        @endphp

        <div class="alert alert-success">
            <strong>Inventory move completed.</strong>

            <div class="mt-2">
                Successfully processed:
                {{ $result['moved_count'] }}
                variants.
            </div>

            @if($result['error_count'] > 0)
                <div class="mt-2 text-danger">
                    Errors:
                    {{ $result['error_count'] }}
                </div>
            @endif
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
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
                            id="vendor"
                            name="vendor"
                            class="form-control"
                            value="{{ old('vendor', $vendor ?? '') }}"
                            placeholder="e.g. Designs for Health"
                            required
                        >

                        <div class="form-text">
                            Enter the vendor exactly as it appears in Shopify.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Preview Inventory
                        </button>
                    </div>

                </div>

            </form>

        </div>
    </div>

    @if(isset($preview))

        <div class="card mb-4">
            <div class="card-body">

                <div class="row">

                    <div class="col-md-3">
                        <strong>Vendor</strong>

                        <div>
                            {{ $preview['vendor'] }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <strong>Destination</strong>

                        <div>
                            {{ $preview['fs_warehouse']['name'] }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <strong>Variants</strong>

                        <div>
                            {{ $preview['variant_count'] }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <strong>Inventory to Move</strong>

                        <div>
                            {{ number_format($preview['inventory_to_move']) }}
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <div class="card mb-4">

            <div class="card-header">
                <strong>Inventory Preview</strong>
            </div>

            <div class="table-responsive">

                <table class="table table-striped table-hover mb-0">

                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>FSWarehouse</th>
                            <th>Other Locations</th>
                            <th>New FSWarehouse</th>
                            <th>Locations</th>
                        </tr>
                    </thead>

                    <tbody>

                    @forelse($preview['variants'] as $row)

                        <tr>

                            <td>
                                {{ $row['product_title'] }}

                                @if($row['variant_title'] !== 'Default Title')
                                    <br>
                                    <small class="text-muted">
                                        {{ $row['variant_title'] }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                {{ $row['sku'] ?: '-' }}
                            </td>

                            <td>
                                {{ number_format($row['fs_quantity']) }}
                            </td>

                            <td>
                                {{ number_format($row['other_quantity']) }}
                            </td>

                            <td>
                                <strong>
                                    {{ number_format($row['new_fs_quantity']) }}
                                </strong>
                            </td>

                            <td>

                                @forelse($row['other_locations'] as $location)

                                    <div class="small">
                                        {{ $location['name'] }}:
                                        {{ number_format($location['quantity']) }}

                                        @if($location['is_active'])
                                            <span class="text-warning">
                                                Active
                                            </span>
                                        @endif
                                    </div>

                                @empty

                                    <span class="text-muted">
                                        FSWarehouse only
                                    </span>

                                @endforelse

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >
                                No variants found for this vendor.
                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>
        </div>

        @if($preview['variant_count'] > 0)

            <div class="card border-warning">

                <div class="card-body">

                    <h5 class="mb-3">
                        Confirm Inventory Move
                    </h5>

                    <p>
                        This will move the inventory from all other active
                        locations into
                        <strong>
                            {{ $preview['fs_warehouse']['name'] }}
                        </strong>
                        and deactivate the other inventory locations for
                        these variants.
                    </p>

                    <p class="mb-3">
                        The FSWarehouse quantity will become the combined
                        quantity shown in the preview.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('vendor-inventory.move') }}"
                        onsubmit="return confirm(
                            'Are you sure you want to move this vendor inventory to FSWarehouse?'
                        );"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="vendor"
                            value="{{ $preview['vendor'] }}"
                        >

                        <div class="form-check mb-3">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="confirm"
                                name="confirm"
                                value="1"
                                required
                            >

                            <label
                                class="form-check-label"
                                for="confirm"
                            >
                                I have reviewed the inventory changes and
                                want to move this vendor's inventory to
                                FSWarehouse.
                            </label>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-warning"
                        >
                            Move Inventory
                        </button>

                    </form>

                </div>

            </div>

        @endif

    @endif

</div>

@endsection