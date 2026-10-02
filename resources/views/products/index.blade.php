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
                id="syncLatestProducts">

                <i class="bi bi-arrow-repeat me-2"></i>

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
            role="alert">

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
         SEARCH / FILTER CARD
    ================================================================= --}}

    <div class="card products-filter-card">

        <div class="card-body">

            <form
                method="GET"
                action="{{ route('products.index') }}"
                id="productSearchForm">

                <div class="row g-3 align-items-end">

                    {{-- ====================================================
                         BRAND
                    ===================================================== --}}

                    <div class="col-lg-5">

                        <label
                            for="brandSearch"
                            class="form-label products-form-label">

                            Brand / Vendor

                        </label>

                        <div class="input-group">

                            <input
                                type="text"
                                id="brandSearch"
                                name="brand"
                                class="form-control products-search-input"
                                placeholder="Search brand..."
                                value="{{ $brand ?? '' }}">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-search me-1"></i>

                                Search

                            </button>

                        </div>

                    </div>


                    {{-- ====================================================
                         PRODUCT / SKU
                    ===================================================== --}}

                    <div class="col-lg-4">

                        <label
                            for="productSearch"
                            class="form-label products-form-label">

                            Product Name / SKU
                            <span class="text-muted">(optional)</span>

                        </label>

                        <div class="input-group">

                            <input
                                type="text"
                                id="productSearch"
                                name="search"
                                class="form-control products-search-input"
                                placeholder="Enter product name or SKU"
                                value="{{ $search ?? '' }}">

                            <button
                                type="submit"
                                class="btn btn-light products-search-icon">

                                <i class="bi bi-search"></i>

                            </button>

                        </div>

                    </div>


                    {{-- ====================================================
                         PER PAGE
                    ===================================================== --}}

                    <div class="col-lg-3">

                        <label
                            for="perPage"
                            class="form-label products-form-label">

                            Show per page

                        </label>

                        <select
                            id="perPage"
                            name="per_page"
                            class="form-select products-search-input"
                            onchange="this.form.submit()">

                            <option
                                value="25"
                                {{ ($perPage ?? 25) == 25 ? 'selected' : '' }}>

                                25

                            </option>

                            <option
                                value="50"
                                {{ ($perPage ?? 25) == 50 ? 'selected' : '' }}>

                                50

                            </option>

                            <option
                                value="100"
                                {{ ($perPage ?? 25) == 100 ? 'selected' : '' }}>

                                100

                            </option>

                        </select>

                    </div>

                </div>

            </form>


            {{-- ============================================================
                 QUICK FILTERS
            ============================================================= --}}

            <div class="quick-filters">

                <span class="quick-filter-label">
                    Quick Filters:
                </span>


                @foreach($brands ?? [] as $quickBrand)

                    @if($loop->first)

                        <a
                            href="{{ route('products.index', [
                                'per_page' => $perPage ?? 25
                            ]) }}"
                            class="brand-chip
                                {{ blank($brand ?? '') ? 'active' : '' }}">

                            {{ $quickBrand }}

                        </a>

                    @else

                        <a
                            href="{{ route('products.index', [
                                'brand' => $quickBrand,
                                'per_page' => $perPage ?? 25
                            ]) }}"
                            class="brand-chip
                                {{ ($brand ?? '') === $quickBrand ? 'active' : '' }}">

                            {{ $quickBrand }}

                        </a>

                    @endif

                @endforeach

            </div>

        </div>

    </div>


    {{-- ================================================================
         BULK ACTION BAR
    ================================================================= --}}

    <div
        class="bulk-action-bar"
        id="bulkActionBar"
        style="display: none;">

        <div class="bulk-selection">

            <div class="bulk-check-icon">

                <i class="bi bi-check-lg"></i>

            </div>

            <strong id="selectedCount">
                0
            </strong>

            products selected

            <span class="bulk-page-count">

                (of
                {{ $paginator->count() }}
                on this page)

            </span>

        </div>


        <div class="bulk-actions">

            <button
                type="button"
                class="btn btn-primary"
                id="pushSelectedButton">

                <i class="bi bi-cloud-arrow-up me-2"></i>

                Push Selected to Shopify
                (<span id="selectedButtonCount">0</span>)

            </button>


            <button
                type="button"
                class="btn btn-light"
                id="clearSelectionButton">

                <i class="bi bi-x-lg me-2"></i>

                Clear Selection

            </button>

        </div>

    </div>


    {{-- ================================================================
         PRODUCT TABLE
    ================================================================= --}}

    <div class="card products-table-card">

        <div class="table-responsive">

            <table class="table products-table mb-0">

                <thead>

                    <tr>

                        {{-- Select --}}
                        <th class="checkbox-column">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="selectAll">

                        </th>


                        {{-- Image --}}
                        <th class="image-column">
                            Image
                        </th>


                        {{-- Product --}}
                        <th>

                            Product

                            <i class="bi bi-chevron-expand sort-icon"></i>

                        </th>


                        {{-- Brand --}}
                        <th>

                            Brand

                            <i class="bi bi-chevron-expand sort-icon"></i>

                        </th>


                        {{-- SKU --}}
                        <th>

                            SKU

                            <i class="bi bi-chevron-expand sort-icon"></i>

                        </th>


                        {{-- Availability --}}
                        <th>

                            Availability

                            <i class="bi bi-chevron-expand sort-icon"></i>

                        </th>


                        {{-- Shopify --}}
                        <th>

                            Shopify Status

                            <i class="bi bi-chevron-expand sort-icon"></i>

                        </th>


                        {{-- Updated --}}
                        <th>

                            Updated

                            <i class="bi bi-chevron-expand sort-icon"></i>

                        </th>


                        {{-- Action --}}
                        <th class="action-column">
                            Action
                        </th>


                        {{-- Menu --}}
                        <th class="menu-column"></th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($products as $product)

                        <tr>

                            {{-- =================================================
                                 CHECKBOX
                            ================================================== --}}

                            <td>

                                <input
                                    type="checkbox"
                                    class="form-check-input product-checkbox"
                                    value="{{ $product['id'] ?? '' }}"
                                    data-sku="{{ $product['sku'] ?? '' }}">

                            </td>


                            {{-- =================================================
                                 IMAGE
                            ================================================== --}}

                            <td>

                                <div class="product-image">

                                    @if(!empty($product['image']))

                                        <img
                                            src="{{ $product['image'] }}"
                                            alt="{{ $product['title'] ?? 'Product' }}"
                                            loading="lazy">

                                    @else

                                        <div class="product-image-placeholder">

                                            <i class="bi bi-box-seam"></i>

                                        </div>

                                    @endif

                                </div>

                            </td>


                            {{-- =================================================
                                 PRODUCT
                            ================================================== --}}

                            <td>

                                <a
                                    href="#"
                                    class="product-name">

                                    {{ $product['title'] ?? 'Untitled Product' }}

                                </a>

                            </td>


                            {{-- =================================================
                                 BRAND
                            ================================================== --}}

                            <td>

                                <span class="brand-name">

                                    {{ $product['brand'] ?? '—' }}

                                </span>

                            </td>


                            {{-- =================================================
                                 SKU
                            ================================================== --}}

                            <td>

                                @if(!empty($product['sku']))

                                    <span class="sku-text">

                                        {{ $product['sku'] }}

                                    </span>

                                @else

                                    <span class="updated-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- =================================================
                                 AVAILABILITY
                            ================================================== --}}

                            <td>

                                @php

                                    $availability =
                                        strtolower(
                                            trim(
                                                (string) (
                                                    $product['availability']
                                                    ?? 'Unknown'
                                                )
                                            )
                                        );

                                    $availabilityClass = match(
                                        $availability
                                    ) {

                                        'in stock' =>
                                            'availability-in-stock',

                                        'backordered' =>
                                            'availability-backordered',

                                        'out of stock' =>
                                            'availability-out-of-stock',

                                        'discontinued' =>
                                            'availability-discontinued',

                                        default =>
                                            'availability-default',
                                    };

                                @endphp


                                <span
                                    class="status-badge {{ $availabilityClass }}">

                                    {{ $product['availability'] ?? 'Unknown' }}

                                </span>

                            </td>


                            {{-- =================================================
                                 SHOPIFY STATUS
                            ================================================== --}}

                            <td>

                                @php

                                    $shopifyStatus =
                                        $product['shopify_status']
                                        ?? 'Not Checked';

                                    $shopifyStatusClass =
                                        match(
                                            strtolower(
                                                trim(
                                                    $shopifyStatus
                                                )
                                            )
                                        ) {

                                            'exists' =>
                                                'shopify-exists',

                                            'not found' =>
                                                'shopify-not-found',

                                            'error' =>
                                                'shopify-error',

                                            'not checked' =>
                                                'shopify-not-checked',

                                            default =>
                                                'shopify-default',
                                        };

                                @endphp


                                <div>

                                    <span
                                        class="status-badge {{ $shopifyStatusClass }}">

                                        @if($shopifyStatus === 'Exists')

                                            <i
                                                class="bi bi-check2-circle me-1">
                                            </i>

                                        @elseif($shopifyStatus === 'Error')

                                            <i
                                                class="bi bi-exclamation-circle me-1">
                                            </i>

                                        @elseif($shopifyStatus === 'Not Found')

                                            <i
                                                class="bi bi-dash-circle me-1">
                                            </i>

                                        @endif

                                        {{ $shopifyStatus }}

                                    </span>


                                    @if(!empty($product['shopify_status_text']))

                                        <div class="shopify-status-text">

                                            {{ $product['shopify_status_text'] }}

                                        </div>

                                    @endif

                                </div>

                            </td>


                            {{-- =================================================
                                 UPDATED
                            ================================================== --}}

                            <td>

                                @if(!empty($product['updated_at']))

                                    @php

                                        try {

                                            $updatedDate =
                                                \Illuminate\Support\Carbon::parse(
                                                    $product['updated_at']
                                                );

                                        } catch (
                                            \Throwable $e
                                        ) {

                                            $updatedDate = null;

                                        }

                                    @endphp


                                    @if($updatedDate)

                                        <span class="updated-text">

                                            {{ $updatedDate->format('M d, Y') }}

                                        </span>

                                        <br>

                                        <span class="updated-time">

                                            {{ $updatedDate->format('h:i A') }}

                                        </span>

                                    @else

                                        <span class="updated-text">

                                            {{ $product['updated_at'] }}

                                        </span>

                                    @endif

                                @else

                                    <span class="updated-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- =================================================
                                 ACTION
                            ================================================== --}}

                            <td>

                                @php

                                    $action =
                                        $product['action']
                                        ?? 'push';

                                @endphp


                                @switch($action)

                                    {{-- ======================================
                                         PUSH
                                    ======================================= --}}

                                    @case('push')

                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm product-action-button"
                                            data-product-id="{{ $product['id'] ?? '' }}"
                                            data-product-sku="{{ $product['sku'] ?? '' }}"
                                            data-action="push">

                                            <i
                                                class="bi bi-cloud-arrow-up me-1">
                                            </i>

                                            Push to Shopify

                                        </button>

                                        @break


                                    {{-- ======================================
                                         UPDATE
                                    ======================================= --}}

                                    @case('update')

                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm product-action-button"
                                            data-product-id="{{ $product['id'] ?? '' }}"
                                            data-product-sku="{{ $product['sku'] ?? '' }}"
                                            data-shopify-product-id="{{ $product['shopify_product_id'] ?? '' }}"
                                            data-action="update">

                                            <i
                                                class="bi bi-arrow-repeat me-1">
                                            </i>

                                            Update in Shopify

                                        </button>

                                        @break


                                    {{-- ======================================
                                         VIEW
                                    ======================================= --}}

                                    @case('view')

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm product-action-button"
                                            data-product-id="{{ $product['id'] ?? '' }}"
                                            data-product-sku="{{ $product['sku'] ?? '' }}"
                                            data-shopify-product-id="{{ $product['shopify_product_id'] ?? '' }}"
                                            data-action="view">

                                            <i
                                                class="bi bi-eye me-1">
                                            </i>

                                            View in Shopify

                                        </button>

                                        @break


                                    {{-- ======================================
                                         RETRY
                                    ======================================= --}}

                                    @case('retry')

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm product-action-button"
                                            data-product-id="{{ $product['id'] ?? '' }}"
                                            data-product-sku="{{ $product['sku'] ?? '' }}"
                                            data-action="retry">

                                            <i
                                                class="bi bi-arrow-repeat me-1">
                                            </i>

                                            Retry Sync

                                        </button>

                                        @break


                                    {{-- ======================================
                                         DEFAULT
                                    ======================================= --}}

                                    @default

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm product-action-button"
                                            disabled>

                                            No Action

                                        </button>

                                @endswitch

                            </td>


                            {{-- =================================================
                                 THREE DOT MENU
                            ================================================== --}}

                            <td>

                                <button
                                    type="button"
                                    class="btn btn-sm product-menu-button"
                                    title="More actions">

                                    <i
                                        class="bi bi-three-dots-vertical">
                                    </i>

                                </button>

                            </td>

                        </tr>

                    @empty

                        {{-- =================================================
                             EMPTY STATE
                        ================================================== --}}

                        <tr>

                            <td
                                colspan="10"
                                class="products-empty-state">

                                <div class="empty-state-icon">

                                    <i class="bi bi-box-seam"></i>

                                </div>

                                <h5>
                                    No products found
                                </h5>

                                <p>
                                    No Fullscript products matched your search.
                                </p>

                                @if(!empty($brand) || !empty($search))

                                    <a
                                        href="{{ route('products.index', [
                                            'per_page' => $perPage ?? 25
                                        ]) }}"
                                        class="btn btn-outline-primary btn-sm">

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

    <div class="products-pagination">

        <div class="pagination-summary">

            @if($paginator->total() > 0)

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

            @else

                No products found

            @endif

        </div>


        @if($paginator->hasPages())

            <nav
                aria-label="Products pagination">

                <ul class="pagination mb-0">

                    {{-- Previous --}}
                    @if($paginator->onFirstPage())

                        <li class="page-item disabled">

                            <span class="page-link">

                                <i
                                    class="bi bi-chevron-left">
                                </i>

                                Previous

                            </span>

                        </li>

                    @else

                        <li class="page-item">

                            <a
                                class="page-link"
                                href="{{ $paginator->previousPageUrl() }}">

                                <i
                                    class="bi bi-chevron-left">
                                </i>

                                Previous

                            </a>

                        </li>

                    @endif


                    {{-- Page Numbers --}}
                    @foreach(
                        $paginator->getUrlRange(
                            max(1, $paginator->currentPage() - 2),
                            min(
                                $paginator->lastPage(),
                                $paginator->currentPage() + 2
                            )
                        ) as $page => $url
                    )

                        <li
                            class="page-item
                                {{ $page == $paginator->currentPage()
                                    ? 'active'
                                    : '' }}">

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
                                    href="{{ $url }}">

                                    {{ $page }}

                                </a>

                            @endif

                        </li>

                    @endforeach


                    {{-- Last page --}}
                    @if(
                        $paginator->lastPage() > 5 &&
                        $paginator->currentPage() <
                        $paginator->lastPage() - 2
                    )

                        <li class="page-item disabled">

                            <span class="page-link">
                                ...
                            </span>

                        </li>

                        <li class="page-item">

                            <a
                                class="page-link"
                                href="{{ $paginator->url(
                                    $paginator->lastPage()
                                ) }}">

                                {{ $paginator->lastPage() }}

                            </a>

                        </li>

                    @endif


                    {{-- Next --}}
                    @if($paginator->hasMorePages())

                        <li class="page-item">

                            <a
                                class="page-link"
                                href="{{ $paginator->nextPageUrl() }}">

                                Next

                                <i
                                    class="bi bi-chevron-right ms-1">
                                </i>

                            </a>

                        </li>

                    @else

                        <li class="page-item disabled">

                            <span class="page-link">

                                Next

                                <i
                                    class="bi bi-chevron-right ms-1">
                                </i>

                            </span>

                        </li>

                    @endif

                </ul>

            </nav>

        @endif

    </div>

</div>


{{-- =====================================================================
     PAGE STYLES
===================================================================== --}}

<style>

    /*
    |--------------------------------------------------------------------------
    | Products Page
    |--------------------------------------------------------------------------
    */

    .products-page {
        width: 100%;
    }


    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    .products-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
    }

    .products-title {
        margin: 0;
        font-size: 32px;
        font-weight: 600;
        color: #1f2937;
        letter-spacing: -0.5px;
    }

    .products-subtitle {
        margin: 4px 0 0;
        color: #6b7280;
        font-size: 15px;
    }

    .products-header-actions {
        text-align: right;
    }

    .products-sync-button {
        min-width: 210px;
        height: 40px;
        font-weight: 500;
        border-radius: 6px;
    }

    .last-synced {
        margin-top: 7px;
        font-size: 13px;
        color: #6b7280;
    }


    /*
    |--------------------------------------------------------------------------
    | Error
    |--------------------------------------------------------------------------
    */

    .products-alert {
        border-radius: 7px;
        margin-bottom: 18px;
        font-size: 14px;
    }


    /*
    |--------------------------------------------------------------------------
    | Search Card
    |--------------------------------------------------------------------------
    */

    .products-filter-card {
        border: 1px solid #e5e7eb;
        border-radius: 7px;
        box-shadow: none;
        margin-bottom: 18px;
    }

    .products-filter-card .card-body {
        padding: 18px;
    }

    .products-form-label {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 7px;
    }

    .products-form-label .text-muted {
        font-weight: 400;
    }

    .products-search-input {
        height: 38px;
        border-color: #d9dee5;
        font-size: 14px;
        box-shadow: none !important;
    }

    .products-search-input:focus {
        border-color: #86b7fe;
    }

    .products-search-icon {
        width: 44px;
        border-color: #d9dee5;
    }


    /*
    |--------------------------------------------------------------------------
    | Quick Filters
    |--------------------------------------------------------------------------
    */

    .quick-filters {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid #f0f1f3;
    }

    .quick-filter-label {
        color: #4b5563;
        font-size: 13px;
        font-weight: 500;
        margin-right: 2px;
    }

    .brand-chip {
        border: 1px solid #e2e5e9;
        background: #f5f6f8;
        color: #4b5563;
        border-radius: 20px;
        padding: 5px 13px;
        font-size: 13px;
        text-decoration: none;
        transition: all .15s ease;
    }

    .brand-chip:hover {
        background: #e9edf2;
        color: #374151;
    }

    .brand-chip.active {
        background: #0d6efd;
        border-color: #0d6efd;
        color: white;
    }


    /*
    |--------------------------------------------------------------------------
    | Bulk Action Bar
    |--------------------------------------------------------------------------
    */

    .bulk-action-bar {
        min-height: 62px;
        padding: 10px 16px;
        margin-bottom: 12px;
        border-radius: 7px;
        background: #eaf5ff;
        border: 1px solid #cce7ff;
        align-items: center;
        justify-content: space-between;
    }

    .bulk-selection {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #1f2937;
        font-size: 14px;
    }

    .bulk-check-icon {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        background: #0d6efd;
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 6px;
        font-size: 13px;
    }

    .bulk-page-count {
        color: #6b7280;
        margin-left: 2px;
    }

    .bulk-actions {
        display: flex;
        gap: 10px;
    }

    .bulk-actions .btn {
        height: 38px;
        font-size: 14px;
        font-weight: 500;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Table
    |--------------------------------------------------------------------------
    */

    .products-table-card {
        border: 1px solid #e1e5ea;
        border-radius: 7px;
        overflow: hidden;
        box-shadow: none;
    }

    .products-table {
        min-width: 1250px;
    }

    .products-table thead th {
        background: #fafafa;
        color: #374151;
        border-bottom: 1px solid #e1e5ea;
        font-size: 12px;
        font-weight: 600;
        padding: 13px 10px;
        white-space: nowrap;
        vertical-align: middle;
    }

    .products-table tbody td {
        padding: 11px 10px;
        border-color: #edf0f2;
        vertical-align: middle;
        font-size: 13px;
        color: #374151;
    }

    .products-table tbody tr:hover {
        background: #fafcff;
    }

    .checkbox-column {
        width: 42px;
        text-align: center;
    }

    .image-column {
        width: 70px;
    }

    .action-column {
        width: 160px;
    }

    .menu-column {
        width: 36px;
    }

    .products-table .form-check-input {
        width: 17px;
        height: 17px;
        cursor: pointer;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Image
    |--------------------------------------------------------------------------
    */

    .product-image {
        width: 46px;
        height: 46px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .product-image img {
        max-width: 46px;
        max-height: 46px;
        object-fit: contain;
    }

    .product-image-placeholder {
        width: 42px;
        height: 42px;
        border-radius: 5px;
        background: #f2f4f7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 20px;
    }


    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    .products-empty-state {
        text-align: center;
        padding: 60px 20px !important;
        color: #6b7280;
    }

    .empty-state-icon {
        width: 58px;
        height: 58px;
        margin: 0 auto 15px;
        border-radius: 10px;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 25px;
        color: #9ca3af;
    }

    .products-empty-state h5 {
        color: #374151;
        margin-bottom: 5px;
    }

    .products-empty-state p {
        margin-bottom: 15px;
        font-size: 14px;
    }


    /*
    |--------------------------------------------------------------------------
    | Product Information
    |--------------------------------------------------------------------------
    */

    .product-name {
        color: #0069d9;
        font-weight: 500;
        text-decoration: none;
        line-height: 1.35;
        display: inline-block;
        max-width: 210px;
    }

    .product-name:hover {
        text-decoration: underline;
    }

    .brand-name {
        color: #4b5563;
        white-space: nowrap;
    }

    .sku-text {
        color: #4b5563;
        font-family: monospace;
        font-size: 12px;
        white-space: nowrap;
    }


    /*
    |--------------------------------------------------------------------------
    | Status Badges
    |--------------------------------------------------------------------------
    */

    .status-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 5px;
        padding: 5px 9px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
    }

    .availability-in-stock {
        background: #dff6e7;
        color: #18763a;
    }

    .availability-backordered {
        background: #fff1c7;
        color: #946200;
    }

    .availability-out-of-stock {
        background: #fde0e3;
        color: #b4232c;
    }

    .availability-discontinued {
        background: #e9dff7;
        color: #6941a5;
    }

    .availability-default {
        background: #eef0f2;
        color: #59636e;
    }


    /*
    |--------------------------------------------------------------------------
    | Shopify Status
    |--------------------------------------------------------------------------
    */

    .shopify-exists {
        background: #dcecff;
        color: #1769aa;
    }

    .shopify-not-found {
        background: #fff0c8;
        color: #996500;
    }

    .shopify-error {
        background: #fde0e3;
        color: #b4232c;
    }

    .shopify-not-checked {
        background: #eef0f2;
        color: #59636e;
    }

    .shopify-default {
        background: #eef0f2;
        color: #59636e;
    }

    .shopify-status-text {
        color: #7a818a;
        font-size: 11px;
        margin-top: 3px;
    }


    /*
    |--------------------------------------------------------------------------
    | Updated
    |--------------------------------------------------------------------------
    */

    .updated-text {
        font-size: 12px;
        color: #4b5563;
    }

    .updated-time {
        font-size: 11px;
        color: #8a919a;
    }

    .updated-empty {
        color: #9ca3af;
        font-size: 14px;
    }


    /*
    |--------------------------------------------------------------------------
    | Action Buttons
    |--------------------------------------------------------------------------
    */

    .product-action-button {
        white-space: nowrap;
        min-width: 140px;
        height: 34px;
        font-size: 12px;
        font-weight: 500;
    }

    .product-menu-button {
        border: none;
        background: transparent;
        color: #6b7280;
        padding: 4px;
    }

    .product-menu-button:hover {
        color: #111827;
        background: #f3f4f6;
    }


    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    .products-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 14px;
    }

    .pagination-summary {
        color: #6b7280;
        font-size: 13px;
    }

    .products-pagination .pagination {
        margin-bottom: 0;
    }

    .products-pagination .page-link {
        color: #4b5563;
        border-color: #dee2e6;
        font-size: 13px;
        min-width: 34px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .products-pagination .page-item.active .page-link {
        background: #0d6efd;
        border-color: #0d6efd;
        color: white;
    }

    .products-pagination .page-item.disabled .page-link {
        color: #adb5bd;
    }


    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1200px) {

        .products-table {
            min-width: 1150px;
        }

    }


    @media (max-width: 992px) {

        .products-header {
            flex-direction: column;
            gap: 15px;
        }

        .products-header-actions {
            text-align: left;
        }

        .bulk-action-bar {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .products-pagination {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

    }


    @media (max-width: 576px) {

        .products-title {
            font-size: 26px;
        }

        .products-subtitle {
            font-size: 13px;
        }

        .products-sync-button {
            width: 100%;
        }

        .products-header-actions {
            width: 100%;
        }

        .bulk-actions {
            width: 100%;
            flex-direction: column;
        }

        .bulk-actions .btn {
            width: 100%;
        }

    }

</style>


{{-- =====================================================================
     PAGE JAVASCRIPT
===================================================================== --}}

@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Update selection
    |--------------------------------------------------------------------------
    */

    function updateSelectionCount() {

        const checked =
            document.querySelectorAll(
                '.product-checkbox:checked'
            ).length;


        /*
        |--------------------------------------------------------------------------
        | Counts
        |--------------------------------------------------------------------------
        */

        if (selectedCount) {

            selectedCount.textContent =
                checked;

        }

        if (selectedButtonCount) {

            selectedButtonCount.textContent =
                checked;

        }


        /*
        |--------------------------------------------------------------------------
        | Bulk action bar
        |--------------------------------------------------------------------------
        */

        if (bulkActionBar) {

            bulkActionBar.style.display =
                checked > 0
                    ? 'flex'
                    : 'none';

        }


        /*
        |--------------------------------------------------------------------------
        | Select all state
        |--------------------------------------------------------------------------
        */

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


    /*
    |--------------------------------------------------------------------------
    | Select all
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Individual checkbox
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Clear selection
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Bulk Push
    |--------------------------------------------------------------------------
    |
    | Step 2:
    | We only collect the selected IDs.
    |
    | The actual API request will be implemented in Step 3.
    |
    */

    if (pushSelectedButton) {

        pushSelectedButton.addEventListener(
            'click',
            function () {

                const selected =
                    Array.from(
                        document.querySelectorAll(
                            '.product-checkbox:checked'
                        )
                    ).map(
                        function (checkbox) {

                            return {
                                id:
                                    checkbox.value,

                                sku:
                                    checkbox.dataset.sku
                                    || null
                            };

                        }
                    );


                if (!selected.length) {

                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | Temporary confirmation
                |--------------------------------------------------------------------------
                */

                const message =
                    'You have selected ' +
                    selected.length +
                    ' product(s).\n\n' +
                    'Bulk Shopify sync will be connected in the next step.';

                alert(message);

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Individual product actions
    |--------------------------------------------------------------------------
    |
    | Actual endpoints will be connected in Step 3.
    |
    */

    document.querySelectorAll(
        '.product-action-button'
    ).forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    const action =
                        button.dataset.action;

                    if (!action) {
                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Step 2 placeholder
                    |--------------------------------------------------------------------------
                    */

                    if (
                        action === 'push' ||
                        action === 'update' ||
                        action === 'retry'
                    ) {

                        alert(
                            'The "' +
                            action +
                            '" action will be connected in the next step.'
                        );

                    }

                }
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Sync Latest Products
    |--------------------------------------------------------------------------
    */

    const syncButton =
        document.getElementById(
            'syncLatestProducts'
        );

    if (syncButton) {

        syncButton.addEventListener(
            'click',
            function () {

                const originalHtml =
                    syncButton.innerHTML;


                syncButton.disabled =
                    true;


                syncButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>' +
                    'Syncing...';


                /*
                |--------------------------------------------------------------------------
                | Step 2:
                | Reload the Products page.
                |
                | The actual catalog synchronization/history will be
                | implemented later.
                |--------------------------------------------------------------------------
                */

                setTimeout(
                    function () {

                        window.location.reload();

                    },
                    500
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Initial state
    |--------------------------------------------------------------------------
    */

    updateSelectionCount();

});

</script>

@endpush

@endsection