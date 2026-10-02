@extends('layouts.app')

@section('title', 'Products - CWFSAPI')

@section('page-title', 'Products')

@section('content')

<div class="products-page">

    {{-- ================================================================
         PAGE HEADER
    ================================================================= --}}

    <div class="products-header">

        <div>

            <h1 class="products-title">
                Products
            </h1>

            <p class="products-subtitle">
                Browse Fullscript products and push them to Shopify.
                Existing products will be updated, new products will be created.
            </p>

        </div>


        <div class="products-header-actions">

            <button
                type="button"
                class="btn btn-primary products-sync-button"
                id="syncLatestProducts"
            >
                <i class="bi bi-arrow-repeat me-1"></i>
                Sync Latest Products
            </button>

            @if(!empty($lastSyncedAt))

                <div class="last-synced">

                    Last synced:
                    {{ $lastSyncedAt }}

                </div>

            @endif

        </div>

    </div>


    {{-- ================================================================
         ERROR MESSAGE
    ================================================================= --}}

    @if(!empty($error))

        <div
            class="alert alert-danger products-alert"
            role="alert"
        >

            <div class="d-flex align-items-start">

                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <div>

                    <strong>
                        Unable to load Fullscript products.
                    </strong>

                    <div class="mt-1">
                        {{ $error }}
                    </div>

                </div>

            </div>

        </div>

    @endif


    {{-- ================================================================
         FILTER CARD
    ================================================================= --}}

    <div class="card products-filter-card">

        <div class="card-body">

            <form
                method="GET"
                action="{{ route('products.index') }}"
                id="productSearchForm"
            >

                <div class="row g-3 align-items-end">

                    {{-- ====================================================
                         BRAND
                    ===================================================== --}}

                    <div class="col-lg-5">

                        <label
                            for="brandId"
                            class="form-label products-form-label"
                        >
                            Brand / Vendor
                        </label>

                        <select
                            name="brand_id"
                            id="brandId"
                            class="form-select products-search-input"
                        >

                            <option value="">
                                All Brands
                            </option>

                            @php
                                $currentBrandId = request(
                                    'brand_id',
                                    $brandId ?? ''
                                );
                            @endphp


                            @foreach(($brands ?? []) as $brandOption)

                                @php

                                    /*
                                     * Support several possible response
                                     * structures from Fullscript.
                                     */

                                    $optionId =
                                        $brandOption['id']
                                        ?? $brandOption['brand_id']
                                        ?? null;

                                    $optionName =
                                        $brandOption['name']
                                        ?? $brandOption['brand_name']
                                        ?? 'Unknown Brand';

                                @endphp


                                @if($optionId)

                                    <option
                                        value="{{ $optionId }}"
                                        @selected(
                                            (string) $currentBrandId ===
                                            (string) $optionId
                                        )
                                    >
                                        {{ $optionName }}
                                    </option>

                                @endif

                            @endforeach

                        </select>

                    </div>


                    {{-- ====================================================
                         PRODUCT / SKU SEARCH
                    ===================================================== --}}

                    <div class="col-lg-4">

                        <label
                            for="productSearch"
                            class="form-label products-form-label"
                        >
                            Product Name / SKU
                            <span class="text-muted">(optional)</span>
                        </label>

                        <div class="input-group">

                            <input
                                type="text"
                                id="productSearch"
                                name="search"
                                class="form-control products-search-input"
                                value="{{ $search ?? request('search', '') }}"
                                placeholder="Search product name or SKU..."
                            >

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-search"></i>
                            </button>

                        </div>

                    </div>


                    {{-- ====================================================
                         PER PAGE
                    ===================================================== --}}

                    <div class="col-lg-2">

                        <label
                            for="perPage"
                            class="form-label products-form-label"
                        >
                            Show per page
                        </label>

                        <select
                            name="per_page"
                            id="perPage"
                            class="form-select products-search-input"
                        >

                            @foreach([25, 50, 100] as $size)

                                <option
                                    value="{{ $size }}"
                                    @selected(
                                        (int)($perPage ?? request('per_page', 25))
                                        === $size
                                    )
                                >
                                    {{ $size }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- ====================================================
                         SEARCH BUTTON
                    ===================================================== --}}

                    <div class="col-lg-1">

                        <button
                            type="submit"
                            class="btn btn-primary products-filter-button"
                            title="Search"
                        >

                            <i class="bi bi-search"></i>

                        </button>

                    </div>

                </div>


                {{-- ========================================================
                     QUICK BRAND FILTERS
                ========================================================= --}}

                @if(!empty($brands))

                    <div class="quick-brand-filter">

                        <span class="quick-filter-label">
                            Quick Filters:
                        </span>


                        <a
                            href="{{ route('products.index', array_merge(
                                request()->except('page', 'brand_id'),
                                []
                            )) }}"
                            class="brand-pill
                                {{ empty($currentBrandId) ? 'active' : '' }}"
                        >
                            All Brands
                        </a>


                        @foreach(
                            collect($brands)->take(6)
                            as $quickBrand
                        )

                            @php

                                $quickBrandId =
                                    $quickBrand['id']
                                    ?? $quickBrand['brand_id']
                                    ?? null;

                                $quickBrandName =
                                    $quickBrand['name']
                                    ?? $quickBrand['brand_name']
                                    ?? 'Unknown Brand';

                            @endphp


                            @if($quickBrandId)

                                <a
                                    href="{{ route(
                                        'products.index',
                                        array_merge(
                                            request()->except('page'),
                                            [
                                                'brand_id' => $quickBrandId
                                            ]
                                        )
                                    ) }}"
                                    class="brand-pill
                                        {{ (string)$currentBrandId ===
                                           (string)$quickBrandId
                                            ? 'active'
                                            : '' }}"
                                >

                                    {{ $quickBrandName }}

                                </a>

                            @endif

                        @endforeach

                    </div>

                @endif

            </form>

        </div>

    </div>


    {{-- ================================================================
         SELECTION / BULK ACTION BAR
    ================================================================= --}}

    <div
        class="bulk-action-bar"
        id="bulkActionBar"
        style="display:none;"
    >

        <div class="bulk-selection-info">

            <i class="bi bi-check2-square me-1"></i>

            <strong id="selectedCount">0</strong>

            products selected

            <span class="text-muted">
                (of {{ $paginator->count() }} on this page)
            </span>

        </div>


        <div class="bulk-actions">

            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="pushSelectedButton"
            >

                <i class="bi bi-cloud-arrow-up me-1"></i>

                Push Selected to Shopify

                (<span id="selectedButtonCount">0</span>)

            </button>


            <button
                type="button"
                class="btn btn-light btn-sm"
                id="clearSelectionButton"
            >

                <i class="bi bi-x-lg me-1"></i>

                Clear Selection

            </button>

        </div>

    </div>


    {{-- ================================================================
         BULK PROGRESS
    ================================================================= --}}

    <div
        class="bulk-progress-card"
        id="bulkProgressCard"
        style="display:none;"
    >

        <div class="bulk-progress-header">

            <span id="bulkProgressText">
                Processing products...
            </span>

            <span id="bulkProgressPercent">
                0%
            </span>

        </div>

        <div class="progress">

            <div
                class="progress-bar"
                id="bulkProgressBar"
                role="progressbar"
                style="width:0%;"
            ></div>

        </div>

    </div>


    {{-- ================================================================
         PRODUCTS TABLE
    ================================================================= --}}

    <div class="card products-table-card">

        <div class="table-responsive">

            <table class="table products-table mb-0">

                <thead>

                    <tr>

                        <th class="checkbox-column">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="selectAll"
                            >

                        </th>


                        <th class="image-column">
                            Image
                        </th>


                        <th class="product-column">
                            Product
                        </th>


                        <th class="brand-column">
                            Brand
                        </th>


                        <th class="sku-column">
                            SKU
                        </th>


                        <th class="availability-column">
                            Availability
                        </th>


                        <th class="shopify-column">
                            Shopify Status
                        </th>


                        <th class="updated-column">
                            Updated
                        </th>


                        <th class="action-column">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($paginator as $product)

                        @php

                            $productId =
                                $product['id']
                                ?? $product['product_id']
                                ?? '';

                            $title =
                                $product['title']
                                ?? $product['name']
                                ?? 'Untitled Product';

                            $brandName =
                                $product['brand']
                                ?? $product['brand_name']
                                ?? '—';

                            /*
                             * Brand may be returned as an object.
                             */
                            if (is_array($brandName)) {

                                $brandName =
                                    $brandName['name']
                                    ?? '—';

                            }


                            $sku =
                                $product['sku']
                                ?? $product['primary_sku']
                                ?? '—';


                            $image =
                                $product['image']
                                ?? $product['image_url']
                                ?? $product['image_url_small']
                                ?? null;


                            $availability =
                                $product['availability']
                                ?? $product['status']
                                ?? 'Unknown';


                            $availabilityLower =
                                strtolower(
                                    (string) $availability
                                );


                            if (
                                str_contains(
                                    $availabilityLower,
                                    'in stock'
                                ) ||
                                $availabilityLower === 'available'
                            ) {

                                $availabilityClass =
                                    'availability-in-stock';

                            } elseif (
                                str_contains(
                                    $availabilityLower,
                                    'backorder'
                                )
                            ) {

                                $availabilityClass =
                                    'availability-backordered';

                            } elseif (
                                str_contains(
                                    $availabilityLower,
                                    'discontinued'
                                )
                            ) {

                                $availabilityClass =
                                    'availability-discontinued';

                            } elseif (
                                str_contains(
                                    $availabilityLower,
                                    'out'
                                ) ||
                                str_contains(
                                    $availabilityLower,
                                    'unavailable'
                                )
                            ) {

                                $availabilityClass =
                                    'availability-out-of-stock';

                            } else {

                                $availabilityClass =
                                    'availability-default';

                            }


                            $shopifyStatus =
                                $product['shopify_status']
                                ?? $product['shopify']['status']
                                ?? 'Not Checked';


                            $shopifyStatusText =
                                $product['shopify_status_text']
                                ?? $product['shopify']['message']
                                ?? '';


                            $shopifyStatusLower =
                                strtolower(
                                    (string) $shopifyStatus
                                );


                            if (
                                str_contains(
                                    $shopifyStatusLower,
                                    'exist'
                                )
                            ) {

                                $shopifyClass =
                                    'shopify-exists';

                            } elseif (
                                str_contains(
                                    $shopifyStatusLower,
                                    'not found'
                                )
                            ) {

                                $shopifyClass =
                                    'shopify-not-found';

                            } elseif (
                                str_contains(
                                    $shopifyStatusLower,
                                    'error'
                                )
                            ) {

                                $shopifyClass =
                                    'shopify-error';

                            } else {

                                $shopifyClass =
                                    'shopify-not-checked';

                            }


                            $updatedAt =
                                $product['updated_at']
                                ?? null;

                            $shopifyProductId =
                                $product['shopify_product_id']
                                ?? $product['shopify']['id']
                                ?? null;


                            $action =
                                $product['action']
                                ?? (
                                    $shopifyProductId
                                        ? 'update'
                                        : 'create'
                                );

                        @endphp


                        <tr>

                            {{-- Checkbox --}}
                            <td class="checkbox-column">

                                <input
                                    type="checkbox"
                                    class="form-check-input product-checkbox"
                                    value="{{ $productId }}"
                                    data-product-id="{{ $productId }}"
                                >

                            </td>


                            {{-- Image --}}
                            <td class="image-column">

                                @if($image)

                                    <img
                                        src="{{ $image }}"
                                        alt="{{ $title }}"
                                        class="product-image"
                                        loading="lazy"
                                    >

                                @else

                                    <div class="product-image-placeholder">

                                        <i class="bi bi-box"></i>

                                    </div>

                                @endif

                            </td>


                            {{-- Product --}}
                            <td class="product-column">

                                <div class="product-info">

                                    <div class="product-name">

                                        {{ $title }}

                                    </div>


                                    @if($productId)

                                        <div class="product-fullscript-id">

                                            Fullscript ID:
                                            {{ $productId }}

                                        </div>

                                    @endif

                                </div>

                            </td>


                            {{-- Brand --}}
                            <td class="brand-column">

                                <span class="brand-name">

                                    {{ $brandName }}

                                </span>

                            </td>


                            {{-- SKU --}}
                            <td class="sku-column">

                                <span class="sku-text">

                                    {{ $sku }}

                                </span>

                            </td>


                            {{-- Availability --}}
                            <td class="availability-column">

                                <span
                                    class="status-badge
                                        {{ $availabilityClass }}"
                                >

                                    {{ $availability }}

                                </span>

                            </td>


                            {{-- Shopify Status --}}
                            <td class="shopify-column">

                                <span
                                    class="status-badge
                                        {{ $shopifyClass }}"
                                >

                                    {{ $shopifyStatus }}

                                </span>


                                @if($shopifyStatusText)

                                    <div class="shopify-status-text">

                                        {{ $shopifyStatusText }}

                                    </div>

                                @elseif(
                                    $shopifyProductId
                                )

                                    <div class="shopify-status-text">

                                        Will update

                                    </div>

                                @else

                                    <div class="shopify-status-text">

                                        Will create

                                    </div>

                                @endif

                            </td>


                            {{-- Updated --}}
                            <td class="updated-column">

                                @if($updatedAt)

                                    <span class="updated-text">

                                        {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('M d, Y') }}

                                    </span>

                                    <div class="updated-time">

                                        {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('h:i A') }}

                                    </div>

                                @else

                                    <span class="updated-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Action --}}
                            <td class="action-column">

                                <button
                                    type="button"
                                    class="btn
                                        {{ $action === 'update'
                                            ? 'btn-outline-primary'
                                            : 'btn-primary' }}
                                        btn-sm
                                        product-action-button"
                                    data-product-id="{{ $productId }}"
                                    data-product-title="{{ $title }}"
                                    data-action="{{ $action }}"
                                >

                                    @if($action === 'update')

                                        <i class="bi bi-arrow-repeat me-1"></i>

                                        Update Shopify

                                    @else

                                        <i class="bi bi-cloud-arrow-up me-1"></i>

                                        Push to Shopify

                                    @endif

                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="9"
                                class="products-empty-state"
                            >

                                <div class="empty-state-icon">

                                    <i class="bi bi-box-seam"></i>

                                </div>


                                <h5>
                                    No products found
                                </h5>


                                <p>
                                    No Fullscript products match your
                                    current filters.
                                </p>


                                @if(
                                    request()->filled('brand_id') ||
                                    request()->filled('search')
                                )

                                    <a
                                        href="{{ route('products.index') }}"
                                        class="btn btn-outline-primary btn-sm"
                                    >

                                        Clear Search

                                    </a>

                                @endif

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- ================================================================
         PAGINATION
    ================================================================= --}}

    @if($paginator->total() > 0)

        <div class="products-pagination">

            {{-- Summary --}}
            <div class="pagination-summary">

                Showing

                <strong>
                    {{ $paginator->firstItem() }}
                </strong>

                to

                <strong>
                    {{ $paginator->lastItem() }}
                </strong>

                of

                <strong>
                    {{ number_format($paginator->total()) }}
                </strong>

                products

            </div>


            {{-- Pagination --}}
            @if($paginator->hasPages())

                <nav
                    class="products-pagination-nav"
                    aria-label="Products pagination"
                >

                    <ul class="pagination products-pagination-list mb-0">

                        {{-- Previous --}}
                        @if($paginator->onFirstPage())

                            <li class="page-item disabled">

                                <span class="page-link">

                                    <i class="bi bi-chevron-left"></i>

                                    <span>Previous</span>

                                </span>

                            </li>

                        @else

                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="{{ $paginator->previousPageUrl() }}"
                                >

                                    <i class="bi bi-chevron-left"></i>

                                    <span>Previous</span>

                                </a>

                            </li>

                        @endif


                        @php

                            $startPage = max(
                                1,
                                $paginator->currentPage() - 2
                            );

                            $endPage = min(
                                $paginator->lastPage(),
                                $paginator->currentPage() + 2
                            );

                        @endphp


                        {{-- First page --}}
                        @if($startPage > 1)

                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="{{ $paginator->url(1) }}"
                                >
                                    1
                                </a>

                            </li>


                            @if($startPage > 2)

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>

                            @endif

                        @endif


                        {{-- Page numbers --}}
                        @foreach(
                            $paginator->getUrlRange(
                                $startPage,
                                $endPage
                            ) as $page => $url
                        )

                            <li
                                class="page-item
                                    {{ $page == $paginator->currentPage()
                                        ? 'active'
                                        : '' }}"
                            >

                                @if(
                                    $page ==
                                    $paginator->currentPage()
                                )

                                    <span class="page-link">

                                        {{ $page }}

                                    </span>

                                @else

                                    <a
                                        class="page-link"
                                        href="{{ $url }}"
                                    >

                                        {{ $page }}

                                    </a>

                                @endif

                            </li>

                        @endforeach


                        {{-- Last page --}}
                        @if(
                            $endPage <
                            $paginator->lastPage()
                        )

                            @if(
                                $endPage <
                                $paginator->lastPage() - 1
                            )

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>

                            @endif


                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="{{ $paginator->url(
                                        $paginator->lastPage()
                                    ) }}"
                                >

                                    {{ $paginator->lastPage() }}

                                </a>

                            </li>

                        @endif


                        {{-- Next --}}
                        @if($paginator->hasMorePages())

                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="{{ $paginator->nextPageUrl() }}"
                                >

                                    <span>Next</span>

                                    <i class="bi bi-chevron-right"></i>

                                </a>

                            </li>

                        @else

                            <li class="page-item disabled">

                                <span class="page-link">

                                    <span>Next</span>

                                    <i class="bi bi-chevron-right"></i>

                                </span>

                            </li>

                        @endif

                    </ul>

                </nav>

            @endif

        </div>

    @endif

</div>


{{-- =====================================================================
     PAGE STYLES
===================================================================== --}}

<style>

    /* ================================================================
       PAGE
    ================================================================= */

    .products-page {
        width: 100%;
        max-width: 100%;
    }


    /* ================================================================
       HEADER
    ================================================================= */

    .products-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;

        margin-bottom: 18px;
    }

    .products-title {
        margin: 0;

        font-size: 28px;
        font-weight: 600;

        color: #1f2937;
        letter-spacing: -0.4px;
    }

    .products-subtitle {
        margin: 4px 0 0;

        color: #6b7280;
        font-size: 13px;
    }

    .products-header-actions {
        text-align: right;
    }

    .products-sync-button {
        height: 36px;

        padding: 0 14px;

        font-size: 12px;
        font-weight: 500;
    }

    .last-synced {
        margin-top: 4px;

        color: #8a919a;
        font-size: 10px;
    }


    /* ================================================================
       ALERT
    ================================================================= */

    .products-alert {
        margin-bottom: 14px;

        padding: 10px 13px;

        font-size: 12px;
    }


    /* ================================================================
       FILTER CARD
    ================================================================= */

    .products-filter-card {
        margin-bottom: 12px;

        border: 1px solid #e2e6ea;
        border-radius: 7px;

        box-shadow: none;
    }

    .products-filter-card .card-body {
        padding: 13px 14px;
    }

    .products-form-label {
        margin-bottom: 5px;

        color: #374151;
        font-size: 11px;
        font-weight: 600;
    }

    .products-search-input {
        height: 34px;

        border-color: #d8dde3;

        font-size: 12px;
    }

    .products-filter-button {
        width: 100%;
        height: 34px;

        padding: 0;

        font-size: 12px;
    }


    /* ================================================================
       QUICK BRAND FILTERS
    ================================================================= */

    .quick-brand-filter {
        display: flex;
        align-items: center;
        flex-wrap: wrap;

        gap: 5px;

        margin-top: 12px;
    }

    .quick-filter-label {
        margin-right: 3px;

        color: #6b7280;
        font-size: 11px;
        font-weight: 600;
    }

    .brand-pill {
        display: inline-flex;
        align-items: center;

        height: 25px;

        padding: 0 9px;

        border: 1px solid #e0e4e8;
        border-radius: 20px;

        background: #ffffff;

        color: #59636e;

        font-size: 10px;
        text-decoration: none;

        transition: all 0.15s ease;
    }

    .brand-pill:hover {
        background: #f3f6f9;

        color: #0d6efd;

        border-color: #b8d0f0;
    }

    .brand-pill.active {
        background: #1f2937;

        color: #ffffff;

        border-color: #1f2937;
    }


    /* ================================================================
       BULK ACTION BAR
    ================================================================= */

    .bulk-action-bar {
        display: flex;

        align-items: center;
        justify-content: space-between;

        min-height: 44px;

        margin-bottom: 10px;

        padding: 7px 10px;

        border: 1px solid #cfe2ff;
        border-radius: 6px;

        background: #f1f7ff;
    }

    .bulk-selection-info {
        color: #374151;

        font-size: 12px;
    }

    .bulk-actions {
        display: flex;

        gap: 6px;
    }

    .bulk-actions .btn {
        font-size: 11px;
    }


    /* ================================================================
       PROGRESS
    ================================================================= */

    .bulk-progress-card {
        margin-bottom: 10px;

        padding: 10px;

        border: 1px solid #dce3ea;
        border-radius: 6px;

        background: #ffffff;
    }

    .bulk-progress-header {
        display: flex;
        justify-content: space-between;

        margin-bottom: 6px;

        color: #4b5563;
        font-size: 11px;
    }

    .bulk-progress-card .progress {
        height: 6px;
    }


    /* ================================================================
       TABLE CARD
    ================================================================= */

    .products-table-card {
        width: 100%;

        border: 1px solid #e1e5ea;
        border-radius: 7px;

        overflow: hidden;

        box-shadow: none;
    }

    .products-table-card .table-responsive {
        width: 100%;

        overflow-x: auto;
    }


    /* ================================================================
       TABLE
    ================================================================= */

    .products-table {
        width: 100%;

        min-width: 1080px;

        margin: 0;

        table-layout: auto;

        font-size: 11px;
    }

    .products-table thead th {
        height: 34px;

        padding: 7px 8px;

        border-bottom: 1px solid #e1e5ea;

        background: #f8fafc;

        color: #6b7280;

        font-size: 9px;
        font-weight: 700;

        text-transform: uppercase;
        letter-spacing: 0.3px;

        white-space: nowrap;
    }

    .products-table tbody td {
        padding: 7px 8px;

        border-bottom: 1px solid #edf0f2;

        vertical-align: middle;

        color: #374151;
    }

    .products-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .products-table tbody tr:hover {
        background: #fafcff;
    }


    /* ================================================================
       COLUMNS
    ================================================================= */

    .checkbox-column {
        width: 36px;

        text-align: center;
    }

    .image-column {
        width: 52px;
    }

    .product-column {
        min-width: 260px;
    }

    .brand-column {
        min-width: 145px;
    }

    .sku-column {
        min-width: 100px;
    }

    .availability-column {
        min-width: 105px;
    }

    .shopify-column {
        min-width: 125px;
    }

    .updated-column {
        min-width: 105px;
    }

    .action-column {
        width: 145px;

        text-align: right;
    }


    /* ================================================================
       PRODUCT IMAGE
    ================================================================= */

    .product-image {
        width: 34px;
        height: 34px;

        object-fit: contain;

        display: block;

        border: 1px solid #e5e7eb;
        border-radius: 5px;

        background: #ffffff;
    }

    .product-image-placeholder {
        width: 34px;
        height: 34px;

        display: flex;

        align-items: center;
        justify-content: center;

        border: 1px solid #e5e7eb;
        border-radius: 5px;

        background: #f8fafc;

        color: #9ca3af;

        font-size: 15px;
    }


    /* ================================================================
       PRODUCT
    ================================================================= */

    .product-info {
        max-width: 330px;
    }

    .product-name {
        color: #1f2937;

        font-size: 11px;
        font-weight: 600;

        line-height: 1.35;
    }

    .product-fullscript-id {
        max-width: 300px;

        margin-top: 2px;

        overflow: hidden;

        color: #9ca3af;

        font-family: monospace;
        font-size: 8px;

        text-overflow: ellipsis;

        white-space: nowrap;
    }

    .brand-name {
        color: #4b5563;

        font-size: 10px;

        white-space: nowrap;
    }

    .sku-text {
        color: #4b5563;

        font-family: monospace;
        font-size: 10px;

        white-space: nowrap;
    }


    /* ================================================================
       STATUS BADGES
    ================================================================= */

    .status-badge {
        display: inline-flex;

        align-items: center;

        min-height: 22px;

        padding: 3px 7px;

        border-radius: 12px;

        font-size: 9px;
        font-weight: 600;

        line-height: 1;

        white-space: nowrap;
    }


    /* Availability */

    .availability-in-stock {
        background: #dcfce7;
        color: #166534;
    }

    .availability-backordered {
        background: #fef3c7;
        color: #92400e;
    }

    .availability-out-of-stock {
        background: #fee2e2;
        color: #991b1b;
    }

    .availability-discontinued {
        background: #ede9fe;
        color: #5b21b6;
    }

    .availability-default {
        background: #f1f3f5;
        color: #59636e;
    }


    /* Shopify */

    .shopify-exists {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .shopify-not-found {
        background: #fef3c7;
        color: #92400e;
    }

    .shopify-error {
        background: #fee2e2;
        color: #991b1b;
    }

    .shopify-not-checked {
        background: #f1f3f5;
        color: #59636e;
    }

    .shopify-status-text {
        margin-top: 2px;

        color: #9ca3af;

        font-size: 8px;
    }


    /* ================================================================
       UPDATED
    ================================================================= */

    .updated-text {
        color: #4b5563;

        font-size: 10px;

        white-space: nowrap;
    }

    .updated-time {
        margin-top: 1px;

        color: #9ca3af;

        font-size: 8px;

        white-space: nowrap;
    }

    .updated-empty {
        color: #9ca3af;

        font-size: 12px;
    }


    /* ================================================================
       ACTION
    ================================================================= */

    .product-action-button {
        min-width: 125px;
        height: 29px;

        padding: 0 8px;

        font-size: 10px;
        font-weight: 500;

        white-space: nowrap;
    }


    /* ================================================================
       EMPTY STATE
    ================================================================= */

    .products-empty-state {
        padding: 55px 20px !important;

        text-align: center;

        color: #6b7280;
    }

    .empty-state-icon {
        width: 50px;
        height: 50px;

        display: flex;

        align-items: center;
        justify-content: center;

        margin: 0 auto 12px;

        border-radius: 10px;

        background: #f3f4f6;

        color: #9ca3af;

        font-size: 22px;
    }

    .products-empty-state h5 {
        margin-bottom: 5px;

        color: #374151;
    }

    .products-empty-state p {
        margin-bottom: 14px;

        font-size: 12px;
    }


    /* ================================================================
       PAGINATION
    ================================================================= */

    .products-pagination {
        width: 100%;

        display: flex;

        align-items: center;
        justify-content: space-between;

        gap: 20px;

        margin-top: 10px;

        padding: 4px 1px 2px;

        min-height: 42px;
    }

    .pagination-summary {
        flex: 0 0 auto;

        color: #6b7280;

        font-size: 11px;
    }

    .pagination-summary strong {
        color: #374151;

        font-weight: 600;
    }

    .products-pagination-nav {
        flex: 0 0 auto;
    }

    .products-pagination-list {
        display: flex;

        align-items: center;

        margin: 0;
        padding: 0;
    }

    .products-pagination-list .page-link {
        width: 32px;
        min-width: 32px;
        height: 32px;

        display: inline-flex;

        align-items: center;
        justify-content: center;

        padding: 0 7px;

        border: 1px solid #dee2e6;

        background: #ffffff;

        color: #4b5563;

        font-size: 11px;

        text-decoration: none;

        box-shadow: none !important;
    }

    .products-pagination-list
        .page-item:not(:first-child)
        .page-link {

        border-left: 0;
    }

    .products-pagination-list
        .page-item:first-child
        .page-link {

        border-radius: 5px 0 0 5px;
    }

    .products-pagination-list
        .page-item:last-child
        .page-link {

        border-radius: 0 5px 5px 0;
    }

    .products-pagination-list
        .page-item.active
        .page-link {

        background: #0d6efd;

        border-color: #0d6efd;

        color: #ffffff;
    }

    .products-pagination-list
        .page-item:not(.active):not(.disabled)
        .page-link:hover {

        background: #f5f7fa;

        color: #0d6efd;
    }

    .products-pagination-list
        .page-item.disabled
        .page-link {

        background: #ffffff;

        color: #adb5bd;

        cursor: default;
    }

    .products-pagination-list
        .page-link i {

        font-size: 10px !important;

        line-height: 1 !important;
    }


    /* ================================================================
       RESPONSIVE
    ================================================================= */

    @media (max-width: 992px) {

        .products-header {
            flex-direction: column;

            gap: 12px;
        }

        .products-header-actions {
            width: 100%;

            text-align: left;
        }

        .products-pagination {
            flex-direction: column;

            align-items: flex-start;

            gap: 8px;
        }

        .products-pagination-nav {
            width: 100%;

            overflow-x: auto;
        }

        .products-pagination-list {
            width: max-content;
        }

    }


    @media (max-width: 576px) {

        .products-title {
            font-size: 24px;
        }

        .products-subtitle {
            font-size: 12px;
        }

        .products-sync-button {
            width: 100%;
        }

        .bulk-action-bar {
            flex-direction: column;

            align-items: flex-start;

            gap: 8px;
        }

        .bulk-actions {
            width: 100%;
        }

        .bulk-actions .btn {
            flex: 1;
        }

    }

</style>


{{-- =====================================================================
     PAGE JAVASCRIPT
===================================================================== --}}

@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    /* ================================================================
       ELEMENTS
    ================================================================= */

    const selectAll =
        document.getElementById('selectAll');

    const productCheckboxes =
        document.querySelectorAll(
            '.product-checkbox'
        );

    const selectedCount =
        document.getElementById(
            'selectedCount'
        );

    const selectedButtonCount =
        document.getElementById(
            'selectedButtonCount'
        );

    const clearSelectionButton =
        document.getElementById(
            'clearSelectionButton'
        );

    const bulkActionBar =
        document.getElementById(
            'bulkActionBar'
        );

    const pushSelectedButton =
        document.getElementById(
            'pushSelectedButton'
        );

    const bulkProgressCard =
        document.getElementById(
            'bulkProgressCard'
        );

    const bulkProgressBar =
        document.getElementById(
            'bulkProgressBar'
        );

    const bulkProgressText =
        document.getElementById(
            'bulkProgressText'
        );

    const bulkProgressPercent =
        document.getElementById(
            'bulkProgressPercent'
        );


    /* ================================================================
       CSRF
    ================================================================= */

    const csrfToken =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.getAttribute('content');


    /* ================================================================
       SELECTION COUNT
    ================================================================= */

    function updateSelectionCount() {

        const checked =
            document.querySelectorAll(
                '.product-checkbox:checked'
            ).length;


        if (selectedCount) {

            selectedCount.textContent =
                checked;

        }


        if (selectedButtonCount) {

            selectedButtonCount.textContent =
                checked;

        }


        if (bulkActionBar) {

            bulkActionBar.style.display =
                checked > 0
                    ? 'flex'
                    : 'none';

        }


        if (selectAll) {

            selectAll.checked =
                checked > 0 &&
                checked ===
                productCheckboxes.length;

            selectAll.indeterminate =
                checked > 0 &&
                checked <
                productCheckboxes.length;

        }

    }


    /* ================================================================
       SELECT ALL
    ================================================================= */

    if (selectAll) {

        selectAll.addEventListener(
            'change',
            function () {

                productCheckboxes.forEach(
                    function (checkbox) {

                        checkbox.checked =
                            selectAll.checked;

                    }
                );

                updateSelectionCount();

            }
        );

    }


    /* ================================================================
       INDIVIDUAL CHECKBOX
    ================================================================= */

    productCheckboxes.forEach(
        function (checkbox) {

            checkbox.addEventListener(
                'change',
                function () {

                    updateSelectionCount();

                }
            );

        }
    );


    /* ================================================================
       CLEAR SELECTION
    ================================================================= */

    if (clearSelectionButton) {

        clearSelectionButton.addEventListener(
            'click',
            function () {

                productCheckboxes.forEach(
                    function (checkbox) {

                        checkbox.checked =
                            false;

                    }
                );

                if (selectAll) {

                    selectAll.checked =
                        false;

                    selectAll.indeterminate =
                        false;

                }

                updateSelectionCount();

            }
        );

    }


    /* ================================================================
       INDIVIDUAL PUSH / UPDATE
    ================================================================= */

    document.querySelectorAll(
        '.product-action-button'
    ).forEach(
        function (button) {

            button.addEventListener(
                'click',
                async function () {

                    const productId =
                        button.dataset.productId;

                    const productTitle =
                        button.dataset.productTitle;

                    if (!productId) {

                        alert(
                            'Product ID is missing.'
                        );

                        return;

                    }


                    const confirmed =
                        confirm(
                            'Push "' +
                            productTitle +
                            '" to Shopify?\n\n' +
                            'If the product already exists, it will be updated.'
                        );

                    if (!confirmed) {

                        return;

                    }


                    const originalHtml =
                        button.innerHTML;


                    button.disabled =
                        true;

                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span>' +
                        'Processing...';


                    try {

                        const url =
                            "{{ route(
                                'products.push',
                                ['productId' => '__PRODUCT_ID__']
                            ) }}"
                            .replace(
                                '__PRODUCT_ID__',
                                encodeURIComponent(
                                    productId
                                )
                            );


                        const response =
                            await fetch(
                                url,
                                {
                                    method: 'POST',

                                    headers: {
                                        'Content-Type':
                                            'application/json',

                                        'Accept':
                                            'application/json',

                                        'X-CSRF-TOKEN':
                                            csrfToken
                                    },

                                    body: JSON.stringify({
                                        product_id:
                                            productId
                                    })
                                }
                            );


                        const data =
                            await response.json();


                        if (!response.ok) {

                            throw new Error(
                                data.message ||
                                'Unable to sync product.'
                            );

                        }


                        alert(
                            data.message ||
                            'Product synced successfully.'
                        );


                        window.location.reload();

                    } catch (error) {

                        alert(
                            error.message ||
                            'An error occurred while syncing the product.'
                        );


                        button.disabled =
                            false;

                        button.innerHTML =
                            originalHtml;

                    }

                }
            );

        }
    );


    /* ================================================================
       BULK PUSH
    ================================================================= */

    if (pushSelectedButton) {

        pushSelectedButton.addEventListener(
            'click',
            async function () {

                const selected =
                    Array.from(
                        document.querySelectorAll(
                            '.product-checkbox:checked'
                        )
                    ).map(
                        checkbox =>
                            checkbox.value
                    );


                if (!selected.length) {

                    alert(
                        'Please select at least one product.'
                    );

                    return;

                }


                const confirmed =
                    confirm(
                        'Push ' +
                        selected.length +
                        ' selected product(s) to Shopify?\n\n' +
                        'Existing products will be updated and new products will be created.'
                    );


                if (!confirmed) {

                    return;

                }


                pushSelectedButton.disabled =
                    true;


                if (bulkProgressCard) {

                    bulkProgressCard.style.display =
                        'block';

                }


                if (bulkProgressText) {

                    bulkProgressText.textContent =
                        'Processing selected products...';

                }


                if (bulkProgressPercent) {

                    bulkProgressPercent.textContent =
                        '0%';

                }


                if (bulkProgressBar) {

                    bulkProgressBar.style.width =
                        '0%';

                }


                try {

                    const response =
                        await fetch(
                            "{{ route('products.push-selected') }}",
                            {
                                method: 'POST',

                                headers: {
                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        csrfToken
                                },

                                body: JSON.stringify({
                                    product_ids:
                                        selected
                                })
                            }
                        );


                    const data =
                        await response.json();


                    if (!response.ok) {

                        throw new Error(
                            data.message ||
                            'Bulk sync failed.'
                        );

                    }


                    if (bulkProgressBar) {

                        bulkProgressBar.style.width =
                            '100%';

                    }


                    if (bulkProgressPercent) {

                        bulkProgressPercent.textContent =
                            '100%';

                    }


                    if (bulkProgressText) {

                        bulkProgressText.textContent =
                            'Sync completed.';

                    }


                    let message =
                        data.message ||
                        'Selected products synced successfully.';


                    if (data.success_count !== undefined) {

                        message +=
                            '\n\nSuccessful: ' +
                            data.success_count;

                    }


                    if (data.failed_count !== undefined) {

                        message +=
                            '\nFailed: ' +
                            data.failed_count;

                    }


                    alert(message);

                    window.location.reload();

                } catch (error) {

                    alert(
                        error.message ||
                        'An error occurred while syncing the selected products.'
                    );


                    pushSelectedButton.disabled =
                        false;

                }

            }
        );

    }


    /* ================================================================
       INITIAL STATE
    ================================================================= */

    updateSelectionCount();

});

</script>

@endpush

@endsection