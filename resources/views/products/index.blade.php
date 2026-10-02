@extends('layouts.app')

@section('title', 'Products')

@section('content')

<div class="products-page">

    {{-- ================================================================
         HEADER
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

        <div class="products-header-right">

            <button
                type="button"
                class="btn btn-primary sync-latest-button"
                id="syncLatestProducts"
            >
                <i class="bi bi-arrow-repeat"></i>
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
         ERROR
    ================================================================= --}}

    @if(!empty($error))

        <div class="alert alert-danger products-error">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            {{ $error }}

        </div>

    @endif


    {{-- ================================================================
         FILTER CARD
    ================================================================= --}}

    <div class="filter-card">

        <form
            method="GET"
            action="{{ route('products.index') }}"
            id="productFilterForm"
        >

            <div class="filter-row">

                {{-- BRAND --}}

                <div class="filter-field brand-field">

                    <label for="brand">
                        Brand / Vendor
                    </label>

                    <div class="search-input-wrapper">

                        <input
                            type="text"
                            name="brand"
                            id="brand"
                            value="{{ $brand ?? '' }}"
                            placeholder="Brand / Vendor"
                            autocomplete="off"
                        >

                        @if(!empty($brand))

                            <button
                                type="button"
                                class="clear-input"
                                onclick="clearBrand()"
                                title="Clear"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>

                        @endif

                    </div>

                </div>


                {{-- SEARCH BUTTON --}}

                <div class="filter-search-button">

                    <button
                        type="submit"
                        class="btn btn-primary search-button"
                    >
                        <i class="bi bi-search"></i>
                        Search
                    </button>

                </div>


                {{-- PRODUCT / SKU --}}

                <div class="filter-field product-search-field">

                    <label for="search">
                        Product Name / SKU
                        <span>(optional)</span>
                    </label>

                    <div class="search-input-wrapper">

                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ $search ?? '' }}"
                            placeholder="Enter product name or SKU"
                        >

                        @if(!empty($search))

                            <button
                                type="button"
                                class="clear-input"
                                onclick="clearSearch()"
                                title="Clear"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>

                        @endif

                    </div>

                </div>


                {{-- SEARCH ICON --}}

                <div class="product-search-icon-wrapper">

                    <button
                        type="submit"
                        class="search-icon-button"
                        title="Search"
                    >
                        <i class="bi bi-search"></i>
                    </button>

                </div>


                {{-- PER PAGE --}}

                <div class="filter-field per-page-field">

                    <label for="per_page">
                        Show per page
                    </label>

                    <select
                        name="per_page"
                        id="per_page"
                        onchange="this.form.submit()"
                    >

                        <option
                            value="25"
                            {{ ($perPage ?? 25) == 25 ? 'selected' : '' }}
                        >
                            25
                        </option>

                        <option
                            value="50"
                            {{ ($perPage ?? 25) == 50 ? 'selected' : '' }}
                        >
                            50
                        </option>

                        <option
                            value="100"
                            {{ ($perPage ?? 25) == 100 ? 'selected' : '' }}
                        >
                            100
                        </option>

                    </select>

                </div>

            </div>


            {{-- ========================================================
                 QUICK FILTERS
            ========================================================= --}}

            <div class="quick-filters">

                <span class="quick-filter-label">
                    Quick Filters:
                </span>

                <a
                    href="{{ route('products.index') }}"
                    class="quick-filter {{ empty($brand) && empty($search) ? 'active' : '' }}"
                >
                    All Brands
                </a>

                <a
                    href="{{ route('products.index', ['brand' => 'Designs for Health']) }}"
                    class="quick-filter {{ ($brand ?? '') === 'Designs for Health' ? 'active' : '' }}"
                >
                    Designs for Health
                </a>

                <a
                    href="{{ route('products.index', ['brand' => 'Allergy Research Group']) }}"
                    class="quick-filter {{ ($brand ?? '') === 'Allergy Research Group' ? 'active' : '' }}"
                >
                    Allergy Research Group
                </a>

                <a
                    href="{{ route('products.index', ['brand' => 'A.C. Grace']) }}"
                    class="quick-filter {{ ($brand ?? '') === 'A.C. Grace' ? 'active' : '' }}"
                >
                    A.C. Grace
                </a>

                <a
                    href="{{ route('products.index', ['brand' => 'Nordic Naturals']) }}"
                    class="quick-filter {{ ($brand ?? '') === 'Nordic Naturals' ? 'active' : '' }}"
                >
                    Nordic Naturals
                </a>

                <a
                    href="{{ route('products.index', ['brand' => 'Thorne']) }}"
                    class="quick-filter {{ ($brand ?? '') === 'Thorne' ? 'active' : '' }}"
                >
                    Thorne
                </a>

                <a
                    href="{{ route('products.index', ['brand' => 'Metagenics']) }}"
                    class="quick-filter {{ ($brand ?? '') === 'Metagenics' ? 'active' : '' }}"
                >
                    Metagenics
                </a>

            </div>

        </form>

    </div>


    {{-- ================================================================
         NO PRODUCTS
    ================================================================= --}}

    @if($products->count() === 0)

        <div class="empty-products">

            <div class="empty-icon">
                <i class="bi bi-box-seam"></i>
            </div>

            @if(!empty($brand) || !empty($search))

                <h3>
                    No products found
                </h3>

                <p>

                    No Fullscript products matched

                    @if(!empty($brand))
                        the brand
                        <strong>
                            "{{ $brand }}"
                        </strong>
                    @endif

                    @if(!empty($search))
                        @if(!empty($brand))
                            and
                        @endif

                        the search
                        <strong>
                            "{{ $search }}"
                        </strong>
                    @endif

                    .

                </p>

                <a
                    href="{{ route('products.index') }}"
                    class="btn btn-outline-primary"
                >
                    Show All Products
                </a>

            @else

                <h3>
                    No products found
                </h3>

                <p>
                    Fullscript did not return any products.
                </p>

            @endif

        </div>

    @else


        {{-- ============================================================
             BULK ACTION BAR
        ============================================================= --}}

        <div
            class="bulk-action-bar"
            id="bulkActionBar"
        >

            <div class="selection-info">

                <input
                    type="checkbox"
                    class="form-check-input"
                    id="selectAllProducts"
                >

                <span>
                    <strong id="selectedCount">
                        0
                    </strong>

                    products selected

                    <span class="selected-total">
                        (of {{ $products->count() }} on this page)
                    </span>
                </span>

            </div>

            <div class="bulk-buttons">

                <button
                    type="button"
                    class="btn btn-primary bulk-push-button"
                    id="pushSelectedButton"
                    disabled
                >
                    <i class="bi bi-cloud-arrow-up"></i>
                    Push Selected to Shopify
                    (<span id="selectedButtonCount">0</span>)
                </button>

                <button
                    type="button"
                    class="btn btn-light clear-selection-button"
                    id="clearSelectionButton"
                >
                    <i class="bi bi-x-lg"></i>
                    Clear Selection
                </button>

            </div>

        </div>


        {{-- ============================================================
             PRODUCTS TABLE
        ============================================================= --}}

        <div class="products-card">

            <div class="table-responsive">

                <table class="products-table">

                    <thead>

                        <tr>

                            <th class="check-column">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="headerSelectAll"
                                >
                            </th>

                            <th class="image-column">
                                Image
                            </th>

                            <th>
                                Product
                            </th>

                            <th>
                                Brand
                            </th>

                            <th>
                                SKU
                            </th>

                            <th>
                                Availability
                            </th>

                            <th>
                                Shopify Status
                            </th>

                            <th>
                                Updated
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach($products as $product)

                            <tr>

                                {{-- CHECKBOX --}}

                                <td class="check-column">

                                    <input
                                        type="checkbox"
                                        class="form-check-input product-checkbox"
                                        value="{{ $product['id'] }}"
                                    >

                                </td>


                                {{-- IMAGE --}}

                                <td class="image-column">

                                    @if(!empty($product['image']))

                                        <img
                                            src="{{ $product['image'] }}"
                                            alt="{{ $product['title'] }}"
                                            class="product-image"
                                            loading="lazy"
                                        >

                                    @else

                                        <div class="product-image-placeholder">

                                            <i class="bi bi-image"></i>

                                        </div>

                                    @endif

                                </td>


                                {{-- PRODUCT --}}

                                <td>

                                    <div class="product-title">
                                        {{ $product['title'] }}
                                    </div>

                                </td>


                                {{-- BRAND --}}

                                <td>

                                    <span class="brand-text">
                                        {{ $product['brand'] ?: '—' }}
                                    </span>

                                </td>


                                {{-- SKU --}}

                                <td>

                                    <span class="sku-text">
                                        {{ $product['sku'] ?: '—' }}
                                    </span>

                                </td>


                                {{-- AVAILABILITY --}}

                                <td>

                                    @php

                                        $availability =
                                            strtolower(
                                                $product['availability'] ?? ''
                                            );

                                    @endphp

                                    @if(
                                        in_array(
                                            $availability,
                                            [
                                                'in stock',
                                                'available'
                                            ],
                                            true
                                        )
                                    )

                                        <span class="status-pill success">
                                            In Stock
                                        </span>

                                    @elseif(
                                        $availability === 'backordered'
                                    )

                                        <span class="status-pill warning">
                                            Backordered
                                        </span>

                                    @elseif(
                                        $availability === 'out of stock'
                                    )

                                        <span class="status-pill danger">
                                            Out of Stock
                                        </span>

                                    @else

                                        <span class="status-pill neutral">
                                            {{ $product['availability'] ?: 'Unknown' }}
                                        </span>

                                    @endif

                                </td>


                                {{-- SHOPIFY STATUS --}}

                                <td>

                                    @if(
                                        ($product['shopify_status'] ?? '')
                                        === 'Exists'
                                    )

                                        <span class="status-pill shopify-exists">
                                            Exists
                                        </span>

                                        <div class="status-subtext">
                                            Will update
                                        </div>

                                    @elseif(
                                        ($product['shopify_status'] ?? '')
                                        === 'Not Found'
                                    )

                                        <span class="status-pill shopify-not-found">
                                            Not Found
                                        </span>

                                        <div class="status-subtext">
                                            Will create
                                        </div>

                                    @else

                                        <span class="status-pill shopify-error">
                                            Error
                                        </span>

                                        <div class="status-subtext">
                                            Sync failed
                                        </div>

                                    @endif

                                </td>


                                {{-- UPDATED --}}

                                <td>

                                    @if(!empty($product['updated_at']))

                                        <span class="updated-text">
                                            {{ $product['updated_at'] }}
                                        </span>

                                    @else

                                        <span class="updated-text">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- ACTION --}}

                                <td>

                                    @if(
                                        ($product['action'] ?? '')
                                        === 'update'
                                    )

                                        <button
                                            type="button"
                                            class="action-button update push-product-button"
                                            data-product-id="{{ $product['id'] }}"
                                        >

                                            <i class="bi bi-arrow-repeat"></i>

                                            Update in Shopify

                                        </button>

                                    @elseif(
                                        ($product['action'] ?? '')
                                        === 'retry'
                                    )

                                        <button
                                            type="button"
                                            class="action-button retry push-product-button"
                                            data-product-id="{{ $product['id'] }}"
                                        >

                                            <i class="bi bi-arrow-repeat"></i>

                                            Retry Sync

                                        </button>

                                    @else

                                        <button
                                            type="button"
                                            class="action-button push push-product-button"
                                            data-product-id="{{ $product['id'] }}"
                                        >

                                            <i class="bi bi-cloud-arrow-up"></i>

                                            Push to Shopify

                                        </button>

                                    @endif

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </div>


        {{-- ============================================================
             PAGINATION
        ============================================================= --}}

        <div class="pagination-area">

            <div class="pagination-summary">

                Showing

                <strong>
                    {{ $products->firstItem() ?? 0 }}
                </strong>

                to

                <strong>
                    {{ $products->lastItem() ?? 0 }}
                </strong>

                of

                <strong>
                    {{ $products->total() }}
                </strong>

                products

            </div>

            @if($products->hasPages())

                <div class="products-pagination">

                    {{ $products
                        ->onEachSide(1)
                        ->links('pagination::bootstrap-5')
                    }}

                </div>

            @endif

        </div>

    @endif

</div>


{{-- =====================================================================
     CSS
===================================================================== --}}

<style>

.products-page {
    width: 100%;
    color: #1f2937;
}

.products-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}

.products-title {
    margin: 0;
    font-size: 32px;
    line-height: 1.2;
    font-weight: 600;
    color: #111827;
}

.products-subtitle {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 15px;
}

.products-header-right {
    text-align: right;
}

.sync-latest-button {
    min-width: 210px;
    height: 40px;
    border-radius: 6px;
    font-weight: 500;
}

.last-synced {
    margin-top: 6px;
    color: #6b7280;
    font-size: 12px;
}

.products-error {
    margin-bottom: 18px;
    border-radius: 7px;
}


/* ================================================================
   FILTER CARD
================================================================ */

.filter-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 7px;
    margin-bottom: 18px;
    padding: 17px 18px 13px;
}

.filter-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.filter-field {
    min-width: 0;
}

.filter-field label {
    display: block;
    margin-bottom: 6px;
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.filter-field label span {
    font-weight: 400;
    color: #6b7280;
}

.brand-field {
    flex: 1.15;
}

.product-search-field {
    flex: 1.35;
}

.per-page-field {
    width: 135px;
}

.filter-field input,
.filter-field select {
    width: 100%;
    height: 38px;
    border: 1px solid #d8dee6;
    border-radius: 5px;
    background: #ffffff;
    color: #374151;
    font-size: 14px;
    padding: 0 12px;
    outline: none;
}

.filter-field input:focus,
.filter-field select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, .08);
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper input {
    padding-right: 38px;
}

.clear-input {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
}

.clear-input:hover {
    color: #111827;
}

.filter-search-button {
    width: 104px;
}

.search-button {
    height: 38px;
    width: 100%;
    border-radius: 5px;
}

.product-search-icon-wrapper {
    width: 40px;
}

.search-icon-button {
    height: 38px;
    width: 40px;
    border: 1px solid #d8dee6;
    border-radius: 5px;
    background: #ffffff;
    color: #374151;
}

.search-icon-button:hover {
    background: #f8fafc;
}

.quick-filters {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    padding-top: 11px;
    border-top: 1px solid #edf0f3;
}

.quick-filter-label {
    margin-right: 2px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.quick-filter {
    display: inline-flex;
    align-items: center;
    height: 28px;
    padding: 0 12px;
    border-radius: 15px;
    background: #f1f3f6;
    color: #4b5563;
    text-decoration: none;
    font-size: 12px;
    transition: all .15s ease;
}

.quick-filter:hover {
    background: #e5e7eb;
    color: #111827;
}

.quick-filter.active {
    background: #1467f4;
    color: #ffffff;
}


/* ================================================================
   EMPTY
================================================================ */

.empty-products {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 70px 20px;
    text-align: center;
}

.empty-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 15px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 24px;
}

.empty-products h3 {
    margin-bottom: 8px;
    font-size: 19px;
}

.empty-products p {
    margin-bottom: 18px;
    color: #6b7280;
}


/* ================================================================
   BULK BAR
================================================================ */

.bulk-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 62px;
    margin-bottom: 12px;
    padding: 10px 16px;
    background: #e9f5ff;
    border: 1px solid #d4ebff;
    border-radius: 6px;
}

.selection-info {
    display: flex;
    align-items: center;
    gap: 13px;
    font-size: 14px;
    color: #1f2937;
}

.selection-info input {
    width: 19px;
    height: 19px;
}

.selected-total {
    color: #374151;
}

.bulk-buttons {
    display: flex;
    gap: 10px;
}

.bulk-push-button {
    height: 40px;
}

.clear-selection-button {
    height: 40px;
    border: 1px solid #d8dee6;
}


/* ================================================================
   TABLE
================================================================ */

.products-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e2e6eb;
    border-radius: 5px;
}

.products-table {
    width: 100%;
    min-width: 1160px;
    border-collapse: collapse;
}

.products-table thead th {
    height: 40px;
    padding: 8px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.products-table tbody td {
    height: 72px;
    padding: 8px 12px;
    border-bottom: 1px solid #edf0f3;
    vertical-align: middle;
    font-size: 13px;
}

.products-table tbody tr:last-child td {
    border-bottom: 0;
}

.products-table tbody tr:hover {
    background: #fbfdff;
}

.check-column {
    width: 45px;
    text-align: center;
}

.image-column {
    width: 72px;
}

.products-table .form-check-input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.product-image {
    width: 48px;
    height: 52px;
    object-fit: contain;
    display: block;
}

.product-image-placeholder {
    width: 48px;
    height: 52px;
    display: flex;
    justify-content: center;
    align-items: center;
    border: 1px solid #e5e7eb;
    border-radius: 5px;
    background: #fafafa;
    color: #9ca3af;
}

.product-title {
    max-width: 280px;
    color: #075fd5;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.35;
}

.brand-text {
    color: #4b5563;
}

.sku-text {
    color: #596579;
    font-size: 13px;
}

.updated-text {
    color: #6b7280;
    font-size: 12px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    min-height: 26px;
    padding: 3px 9px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.status-pill.success {
    background: #dcfce7;
    color: #15803d;
}

.status-pill.warning {
    background: #fef3c7;
    color: #b45309;
}

.status-pill.danger {
    background: #fee2e2;
    color: #dc2626;
}

.status-pill.neutral {
    background: #f3f4f6;
    color: #4b5563;
}

.status-pill.shopify-exists {
    background: #dbeafe;
    color: #2563eb;
}

.status-pill.shopify-not-found {
    background: #fef3c7;
    color: #b45309;
}

.status-pill.shopify-error {
    background: #fee2e2;
    color: #dc2626;
}

.status-subtext {
    margin-top: 3px;
    color: #6b7280;
    font-size: 11px;
}

.action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 160px;
    height: 38px;
    padding: 0 12px;
    border-radius: 5px;
    background: #ffffff;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
}

.action-button.push {
    border: 1px solid #1467f4;
    background: #1467f4;
    color: #ffffff;
}

.action-button.update {
    border: 1px solid #8ab4f8;
    background: #ffffff;
    color: #1467f4;
}

.action-button.retry {
    border: 1px solid #d1d5db;
    color: #374151;
}

.action-button:hover {
    opacity: .9;
}

.action-button:disabled {
    opacity: .6;
    cursor: wait;
}


/* ================================================================
   PAGINATION
================================================================ */

.pagination-area {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
}

.pagination-summary {
    color: #667085;
    font-size: 13px;
}

.products-pagination .pagination {
    margin: 0;
}

.products-pagination .page-link {
    min-width: 36px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    font-size: 13px;
}


/* ================================================================
   RESPONSIVE
================================================================ */

@media (max-width: 1100px) {

    .filter-row {
        flex-wrap: wrap;
    }

    .brand-field,
    .product-search-field {
        flex: 1 1 40%;
    }

    .filter-search-button {
        width: 100px;
    }

}

@media (max-width: 768px) {

    .products-header {
        flex-direction: column;
        gap: 15px;
    }

    .products-header-right {
        width: 100%;
        text-align: left;
    }

    .sync-latest-button {
        width: 100%;
    }

    .filter-row {
        display: grid;
        grid-template-columns: 1fr;
    }

    .brand-field,
    .product-search-field,
    .per-page-field,
    .filter-search-button,
    .product-search-icon-wrapper {
        width: 100%;
    }

    .bulk-action-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .bulk-buttons {
        width: 100%;
        flex-wrap: wrap;
    }

    .pagination-area {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

}

</style>


{{-- =====================================================================
     JAVASCRIPT
===================================================================== --}}

@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const csrfToken =
            document
                .querySelector(
                    'meta[name="csrf-token"]'
                )
                ?.getAttribute(
                    'content'
                );

        const checkboxes =
            Array.from(
                document.querySelectorAll(
                    '.product-checkbox'
                )
            );

        const selectAll =
            document.getElementById(
                'selectAllProducts'
            );

        const headerSelectAll =
            document.getElementById(
                'headerSelectAll'
            );

        const selectedCount =
            document.getElementById(
                'selectedCount'
            );

        const selectedButtonCount =
            document.getElementById(
                'selectedButtonCount'
            );

        const pushSelectedButton =
            document.getElementById(
                'pushSelectedButton'
            );

        const clearSelectionButton =
            document.getElementById(
                'clearSelectionButton'
            );


        /*
        |--------------------------------------------------------------------------
        | Update selection UI
        |--------------------------------------------------------------------------
        */

        function updateSelectionUI() {

            const selected =
                checkboxes.filter(
                    checkbox =>
                        checkbox.checked
                );

            const count =
                selected.length;

            if (selectedCount) {
                selectedCount.textContent =
                    count;
            }

            if (selectedButtonCount) {
                selectedButtonCount.textContent =
                    count;
            }

            if (pushSelectedButton) {
                pushSelectedButton.disabled =
                    count === 0;
            }

            const allSelected =
                checkboxes.length > 0
                && count === checkboxes.length;

            const partiallySelected =
                count > 0
                && count < checkboxes.length;

            if (selectAll) {

                selectAll.checked =
                    allSelected;

                selectAll.indeterminate =
                    partiallySelected;
            }

            if (headerSelectAll) {

                headerSelectAll.checked =
                    allSelected;

                headerSelectAll.indeterminate =
                    partiallySelected;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Select all
        |--------------------------------------------------------------------------
        */

        function selectEverything(
            checked
        ) {

            checkboxes.forEach(
                checkbox => {
                    checkbox.checked =
                        checked;
                }
            );

            updateSelectionUI();
        }


        if (selectAll) {

            selectAll.addEventListener(
                'change',
                function () {
                    selectEverything(
                        selectAll.checked
                    );
                }
            );
        }


        if (headerSelectAll) {

            headerSelectAll.addEventListener(
                'change',
                function () {
                    selectEverything(
                        headerSelectAll.checked
                    );
                }
            );
        }


        checkboxes.forEach(
            checkbox => {

                checkbox.addEventListener(
                    'change',
                    updateSelectionUI
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

                    checkboxes.forEach(
                        checkbox => {
                            checkbox.checked =
                                false;
                        }
                    );

                    updateSelectionUI();
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Individual Push / Update
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.push-product-button'
            )
            .forEach(
                button => {

                    button.addEventListener(
                        'click',
                        async function () {

                            const productId =
                                button.dataset.productId;

                            if (!productId) {
                                return;
                            }

                            const originalHtml =
                                button.innerHTML;

                            button.disabled =
                                true;

                            button.innerHTML =
                                '<span class="spinner-border spinner-border-sm"></span> Processing...';

                            try {

                                const url =
                                    @json(
                                        route(
                                            'products.push',
                                            [
                                                'productId' =>
                                                    '__PRODUCT_ID__'
                                            ]
                                        )
                                    ).replace(
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

                                            body:
                                                JSON.stringify({})
                                        }
                                    );

                                const data =
                                    await response.json();

                                if (
                                    !response.ok
                                    || !data.success
                                ) {

                                    throw new Error(
                                        data.message
                                        || 'Unable to process product.'
                                    );
                                }

                                alert(
                                    data.message
                                    || 'Product processed successfully.'
                                );

                                window.location.reload();

                            } catch (error) {

                                alert(
                                    error.message
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


        /*
        |--------------------------------------------------------------------------
        | Bulk Push
        |--------------------------------------------------------------------------
        */

        if (pushSelectedButton) {

            pushSelectedButton.addEventListener(
                'click',
                async function () {

                    const selected =
                        checkboxes
                            .filter(
                                checkbox =>
                                    checkbox.checked
                            )
                            .map(
                                checkbox =>
                                    checkbox.value
                            );

                    if (
                        selected.length === 0
                    ) {
                        return;
                    }

                    const confirmed =
                        confirm(
                            `Push ${selected.length} selected product(s) to Shopify?`
                        );

                    if (!confirmed) {
                        return;
                    }

                    const originalHtml =
                        pushSelectedButton.innerHTML;

                    pushSelectedButton.disabled =
                        true;

                    pushSelectedButton.innerHTML =
                        '<span class="spinner-border spinner-border-sm"></span> Processing...';

                    try {

                        const response =
                            await fetch(
                                @json(
                                    route(
                                        'products.push-selected'
                                    )
                                ),
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

                                    body:
                                        JSON.stringify(
                                            {
                                                product_ids:
                                                    selected
                                            }
                                        )
                                }
                            );

                        const data =
                            await response.json();

                        if (!response.ok) {

                            throw new Error(
                                data.message
                                || 'Bulk push failed.'
                            );
                        }

                        alert(
                            data.message
                            || 'Products processed successfully.'
                        );

                        window.location.reload();

                    } catch (error) {

                        alert(
                            error.message
                        );

                        pushSelectedButton.disabled =
                            false;

                        pushSelectedButton.innerHTML =
                            originalHtml;
                    }

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Initial state
        |--------------------------------------------------------------------------
        */

        updateSelectionUI();

    }
);


/*
|--------------------------------------------------------------------------
| Clear Brand
|--------------------------------------------------------------------------
*/

function clearBrand()
{
    const input =
        document.getElementById(
            'brand'
        );

    if (input) {
        input.value = '';
    }

    document
        .getElementById(
            'productFilterForm'
        )
        ?.submit();
}


/*
|--------------------------------------------------------------------------
| Clear Product Search
|--------------------------------------------------------------------------
*/

function clearSearch()
{
    const input =
        document.getElementById(
            'search'
        );

    if (input) {
        input.value = '';
    }

    document
        .getElementById(
            'productFilterForm'
        )
        ?.submit();
}

</script>

@endpush

@endsection