@extends('layouts.app')

@section('content')

<div class="container-fluid py-4">

    {{-- ============================================================
         HEADER
    ============================================================= --}}
    <div class="mb-4">

        <h1 class="h3 mb-1">
            Vendor Inventory
        </h1>

        <p class="text-muted mb-0">
            Manage inventory location activation for products.
            Inventory quantities will not be changed.
        </p>

    </div>


    {{-- ============================================================
         ERRORS
    ============================================================= --}}
    @if($errors->any())

        <div class="alert alert-danger">

            <ul class="mb-0">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- ============================================================
         ACTION RESULT
    ============================================================= --}}
    @if(!empty($actionResult))

        <div class="alert {{ ($actionResult['success'] ?? false) ? 'alert-success' : 'alert-warning' }}">

            <div class="fw-semibold">
                {{ $actionResult['message'] ?? 'Action completed.' }}
            </div>

            @if(isset($actionResult['summary']))

                <div class="small mt-1">

                    Total variants:
                    <strong>
                        {{ $actionResult['summary']['total'] ?? 0 }}
                    </strong>

                    &nbsp;|&nbsp;

                    Successful:
                    <strong>
                        {{ $actionResult['summary']['successful'] ?? 0 }}
                    </strong>

                    &nbsp;|&nbsp;

                    Failed:
                    <strong>
                        {{ $actionResult['summary']['failed'] ?? 0 }}
                    </strong>

                </div>

            @endif

        </div>

    @endif


    {{-- ============================================================
         BRAND SELECTION
    ============================================================= --}}
    <div class="row mb-4">

        <div class="col-lg-8">

            <div class="card">

                <div class="card-header fw-semibold">
                    Select Brand
                </div>

                <div class="card-body">

                    <form
                        method="GET"
                        action="{{ route('vendor-inventory.products') }}"
                        class="row g-3 align-items-end"
                    >

                        <div class="col-md-9">

                            <label
                                for="vendor"
                                class="form-label"
                            >
                                Brand / Vendor Name
                            </label>

                            <input
                                type="text"
                                id="vendor"
                                name="vendor"
                                class="form-control"
                                value="{{ $vendor ?? '' }}"
                                placeholder="Enter brand name"
                                required
                            >

                            <div class="form-text">
                                Example: A.C. Grace
                            </div>

                        </div>


                        <div class="col-md-2 d-flex align-items-start">

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                Load Products
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>


        {{-- INFO --}}
        <div class="col-lg-4">

            <div class="alert alert-info h-100 mb-0">

                <div class="fw-semibold mb-1">
                    Important
                </div>

                <div class="small">

                    This tool only changes inventory
                    location activation status.

                    Inventory quantities remain unchanged.

                </div>

            </div>

        </div>

    </div>



    {{-- ============================================================
         PRODUCTS
    ============================================================= --}}
    @if($vendor && !empty($products))

        <div class="card mb-4">

            <div class="card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="fw-semibold">
                            {{ $vendor }}
                        </div>

                        <div class="small text-muted">
                            {{ number_format($totalProducts) }}
                            products
                        </div>

                    </div>


                    <div class="text-muted small">

                        Page {{ $page }}
                        of {{ $totalPages }}

                    </div>

                </div>

            </div>


            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th style="width: 40px;">
                                </th>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Variants
                                </th>

                                <th>
                                    FSWarehouse
                                </th>

                                <th>
                                    Headquarters
                                </th>

                                <th>
                                    Status
                                </th>

                                <th class="text-end">
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @foreach($products as $product)

                                @php

                                    $collapseId =
                                        'product-' .
                                        md5($product['id']);

                                    $fsStatus =
                                        $product['fs_status']
                                        ?? 'inactive';

                                    $hqStatus =
                                        $product['hq_status']
                                        ?? 'inactive';

                                    $productStatus =
                                        $product['status']
                                        ?? 'mixed';

                                @endphp


                                {{-- PRODUCT ROW --}}
                                <tr>

                                    {{-- Expand --}}
                                    <td>

                                        <button
                                            class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $collapseId }}"
                                            aria-expanded="false"
                                            aria-controls="{{ $collapseId }}"
                                        >
                                            <i class="bi bi-chevron-down"></i>
                                        </button>

                                    </td>


                                    {{-- Product --}}
                                    <td>

                                        <div class="d-flex align-items-center">

                                            @if(!empty($product['image']))

                                                <img
                                                    src="{{ $product['image'] }}"
                                                    alt="{{ $product['title'] }}"
                                                    class="rounded me-3"
                                                    width="48"
                                                    height="48"
                                                    style="object-fit: cover;"
                                                >

                                            @endif

                                            <div>

                                                <div class="fw-semibold">
                                                    {{ $product['title'] }}
                                                </div>

                                                <div class="small text-muted">
                                                    {{ $product['id'] }}
                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    {{-- Variants --}}
                                    <td>

                                        <span class="badge bg-secondary">

                                            {{
                                                number_format(
                                                    $product['variant_count'] ?? 0
                                                )
                                            }}

                                        </span>

                                    </td>


                                    {{-- FS --}}
                                    <td>

                                        @if($fsStatus === 'active')

                                            <span class="badge bg-success">
                                                Active
                                            </span>

                                        @elseif($fsStatus === 'mixed')

                                            <span class="badge bg-warning text-dark">
                                                Mixed
                                            </span>

                                        @else

                                            <span class="badge bg-secondary">
                                                Inactive
                                            </span>

                                        @endif

                                    </td>


                                    {{-- HQ --}}
                                    <td>

                                        @if($hqStatus === 'active')

                                            <span class="badge bg-success">
                                                Active
                                            </span>

                                        @elseif($hqStatus === 'mixed')

                                            <span class="badge bg-warning text-dark">
                                                Mixed
                                            </span>

                                        @else

                                            <span class="badge bg-secondary">
                                                Inactive
                                            </span>

                                        @endif

                                    </td>


                                    {{-- Overall --}}
                                    <td>

                                        @switch($productStatus)

                                            @case('both_active')

                                                <span class="badge bg-success">
                                                    Both Active
                                                </span>

                                                @break


                                            @case('fs_only')

                                                <span class="badge bg-primary">
                                                    FS Only
                                                </span>

                                                @break


                                            @case('hq_only')

                                                <span class="badge bg-info text-dark">
                                                    HQ Only
                                                </span>

                                                @break


                                            @case('both_inactive')

                                                <span class="badge bg-secondary">
                                                    Both Inactive
                                                </span>

                                                @break


                                            @default

                                                <span class="badge bg-warning text-dark">
                                                    Mixed
                                                </span>

                                        @endswitch

                                    </td>


                                    {{-- Actions --}}
                                    <td class="text-end">

                                        <div class="dropdown">

                                            <button
                                                class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                type="button"
                                                data-bs-toggle="dropdown"
                                            >
                                                Actions
                                            </button>


                                            <ul class="dropdown-menu dropdown-menu-end">

                                                {{-- Activate FS --}}
                                                @if($fsStatus !== 'active')

                                                    <li>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('vendor-inventory.product.action') }}"
                                                        >

                                                            @csrf

                                                            <input
                                                                type="hidden"
                                                                name="vendor"
                                                                value="{{ $vendor }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="product_id"
                                                                value="{{ $product['id'] }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="activate_fs"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="page"
                                                                value="{{ $page }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="after"
                                                                value="{{ $after }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="before"
                                                                value="{{ $before }}"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item"
                                                            >
                                                                Activate FSWarehouse
                                                            </button>

                                                        </form>

                                                    </li>

                                                @endif


                                                {{-- Activate HQ --}}
                                                @if($hqStatus !== 'active')

                                                    <li>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('vendor-inventory.product.action') }}"
                                                        >

                                                            @csrf

                                                            <input
                                                                type="hidden"
                                                                name="vendor"
                                                                value="{{ $vendor }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="product_id"
                                                                value="{{ $product['id'] }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="activate_hq"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="page"
                                                                value="{{ $page }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="after"
                                                                value="{{ $after }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="before"
                                                                value="{{ $before }}"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item"
                                                            >
                                                                Activate Headquarters
                                                            </button>

                                                        </form>

                                                    </li>

                                                @endif


                                                {{-- Activate Both --}}
                                                @if(
                                                    $fsStatus !== 'active'
                                                    || $hqStatus !== 'active'
                                                )

                                                    <li>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('vendor-inventory.product.action') }}"
                                                        >

                                                            @csrf

                                                            <input
                                                                type="hidden"
                                                                name="vendor"
                                                                value="{{ $vendor }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="product_id"
                                                                value="{{ $product['id'] }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="activate_both"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="page"
                                                                value="{{ $page }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="after"
                                                                value="{{ $after }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="before"
                                                                value="{{ $before }}"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item"
                                                            >
                                                                Activate Both
                                                            </button>

                                                        </form>

                                                    </li>

                                                @endif


                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>


                                                {{-- Deactivate FS --}}
                                                @if($fsStatus === 'active')

                                                    <li>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('vendor-inventory.product.action') }}"
                                                            onsubmit="return confirm('Deactivate FSWarehouse for this product?');"
                                                        >

                                                            @csrf

                                                            <input
                                                                type="hidden"
                                                                name="vendor"
                                                                value="{{ $vendor }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="product_id"
                                                                value="{{ $product['id'] }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="deactivate_fs"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="page"
                                                                value="{{ $page }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="after"
                                                                value="{{ $after }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="before"
                                                                value="{{ $before }}"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item text-danger"
                                                            >
                                                                Deactivate FSWarehouse
                                                            </button>

                                                        </form>

                                                    </li>

                                                @endif


                                                {{-- Deactivate HQ --}}
                                                @if($hqStatus === 'active')

                                                    <li>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('vendor-inventory.product.action') }}"
                                                            onsubmit="return confirm('Deactivate Headquarters for this product?');"
                                                        >

                                                            @csrf

                                                            <input
                                                                type="hidden"
                                                                name="vendor"
                                                                value="{{ $vendor }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="product_id"
                                                                value="{{ $product['id'] }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="deactivate_hq"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="page"
                                                                value="{{ $page }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="after"
                                                                value="{{ $after }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="before"
                                                                value="{{ $before }}"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item text-danger"
                                                            >
                                                                Deactivate Headquarters
                                                            </button>

                                                        </form>

                                                    </li>

                                                @endif


                                                {{-- Deactivate Both --}}
                                                @if(
                                                    $fsStatus === 'active'
                                                    || $hqStatus === 'active'
                                                )

                                                    <li>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('vendor-inventory.product.action') }}"
                                                            onsubmit="return confirm('Deactivate BOTH warehouses for this product?');"
                                                        >

                                                            @csrf

                                                            <input
                                                                type="hidden"
                                                                name="vendor"
                                                                value="{{ $vendor }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="product_id"
                                                                value="{{ $product['id'] }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="deactivate_both"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="page"
                                                                value="{{ $page }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="after"
                                                                value="{{ $after }}"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="before"
                                                                value="{{ $before }}"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item text-danger"
                                                            >
                                                                Deactivate Both
                                                            </button>

                                                        </form>

                                                    </li>

                                                @endif

                                            </ul>

                                        </div>

                                    </td>

                                </tr>


                                {{-- =================================================
                                     VARIANT DETAILS
                                ================================================== --}}
                                <tr>

                                    <td
                                        colspan="7"
                                        class="p-0 border-0"
                                    >

                                        <div
                                            class="collapse"
                                            id="{{ $collapseId }}"
                                        >

                                            <div class="bg-light p-4">

                                                <div class="fw-semibold mb-3">
                                                    Variant Details
                                                </div>


                                                <div class="table-responsive">

                                                    <table class="table table-sm table-bordered bg-white mb-0">

                                                        <thead class="table-light">

                                                            <tr>

                                                                <th>
                                                                    Variant
                                                                </th>

                                                                <th>
                                                                    SKU
                                                                </th>

                                                                <th>
                                                                    FSWarehouse
                                                                </th>

                                                                <th>
                                                                    FS Qty
                                                                </th>

                                                                <th>
                                                                    Headquarters
                                                                </th>

                                                                <th>
                                                                    HQ Qty
                                                                </th>

                                                            </tr>

                                                        </thead>


                                                        <tbody>

                                                            @forelse(
                                                                $product['variants'] ?? []
                                                                as $variant
                                                            )

                                                                <tr>

                                                                    <td>
                                                                        {{ $variant['title'] ?? '-' }}
                                                                    </td>

                                                                    <td>

                                                                        <code>
                                                                            {{ $variant['sku'] ?? '-' }}
                                                                        </code>

                                                                    </td>

                                                                    <td>

                                                                        @if(
                                                                            ($variant['fs_status'] ?? '')
                                                                            === 'active'
                                                                        )

                                                                            <span class="badge bg-success">
                                                                                Active
                                                                            </span>

                                                                        @else

                                                                            <span class="badge bg-secondary">
                                                                                Inactive
                                                                            </span>

                                                                        @endif

                                                                    </td>

                                                                    <td>

                                                                        {{
                                                                            number_format(
                                                                                $variant['fs_quantity']
                                                                                ?? 0
                                                                            )
                                                                        }}

                                                                    </td>

                                                                    <td>

                                                                        @if(
                                                                            ($variant['hq_status'] ?? '')
                                                                            === 'active'
                                                                        )

                                                                            <span class="badge bg-success">
                                                                                Active
                                                                            </span>

                                                                        @else

                                                                            <span class="badge bg-secondary">
                                                                                Inactive
                                                                            </span>

                                                                        @endif

                                                                    </td>

                                                                    <td>

                                                                        {{
                                                                            number_format(
                                                                                $variant['hq_quantity']
                                                                                ?? 0
                                                                            )
                                                                        }}

                                                                    </td>

                                                                </tr>

                                                            @empty

                                                                <tr>

                                                                    <td
                                                                        colspan="6"
                                                                        class="text-center text-muted"
                                                                    >
                                                                        No variants found.

                                                                    </td>

                                                                </tr>

                                                            @endforelse

                                                        </tbody>

                                                    </table>

                                                </div>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        </div>



        {{-- ============================================================
             PAGINATION
        ============================================================= --}}
        <div class="d-flex justify-content-between align-items-center">

            <div class="small text-muted">

                Showing

                {{ (($page - 1) * 25) + 1 }}

                –

                {{
                    min(
                        $page * 25,
                        $totalProducts
                    )
                }}

                of

                {{ number_format($totalProducts) }}

                products

            </div>


            <div class="d-flex gap-2">

                {{-- Previous --}}
                @if($hasPreviousPage)

                    <a
                        href="{{
                            route(
                                'vendor-inventory.products',
                                [
                                    'vendor' =>
                                        $vendor,

                                    'page' =>
                                        max(
                                            1,
                                            $page - 1
                                        ),

                                    'before' =>
                                        $previousCursor,
                                ]
                            )
                        }}"
                        class="btn btn-outline-secondary"
                    >
                        ← Previous
                    </a>

                @else

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        disabled
                    >
                        ← Previous
                    </button>

                @endif


                {{-- Page --}}
                <span class="btn btn-light">
                    Page {{ $page }}
                </span>


                {{-- Next --}}
                @if($hasNextPage)

                    <a
                        href="{{
                            route(
                                'vendor-inventory.products',
                                [
                                    'vendor' =>
                                        $vendor,

                                    'page' =>
                                        $page + 1,

                                    'after' =>
                                        $nextCursor,
                                ]
                            )
                        }}"
                        class="btn btn-outline-primary"
                    >
                        Next →
                    </a>

                @else

                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        disabled
                    >
                        Next →
                    </button>

                @endif

            </div>

        </div>

    @elseif($vendor)

        <div class="alert alert-warning">

            No products were found for:

            <strong>
                {{ $vendor }}
            </strong>

        </div>

    @endif

</div>

@endsection