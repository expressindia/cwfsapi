@extends('layouts.app')

@section('content')

<style>
    .products-page {
        padding: 24px;
    }

    .products-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .products-header h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
    }

    .products-header p {
        margin: 6px 0 0;
        color: #6c757d;
    }

    .sync-info {
        text-align: right;
    }

    .sync-info small {
        display: block;
        color: #6c757d;
        margin-top: 6px;
    }

    .filter-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        padding: 18px;
        margin-bottom: 18px;
    }

    .filter-label {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 7px;
    }

    .brand-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 16px;
    }

    .brand-pill {
        border: 1px solid #dee2e6;
        background: #fff;
        color: #495057;
        border-radius: 20px;
        padding: 7px 14px;
        font-size: 13px;
        text-decoration: none;
    }

    .brand-pill:hover {
        background: #f8f9fa;
        color: #212529;
    }

    .brand-pill.active {
        background: #212529;
        border-color: #212529;
        color: #fff;
    }

    .selection-bar {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        background: #f1f5f9;
        border: 1px solid #dbe3ec;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 14px;
    }

    .selection-bar.show {
        display: flex;
    }

    .selection-count {
        font-weight: 600;
        font-size: 14px;
    }

    .products-card {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
    }

    .products-table {
        margin: 0;
    }

    .products-table thead th {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6c757d;
        white-space: nowrap;
        padding: 13px 12px;
    }

    .products-table tbody td {
        vertical-align: middle;
        padding: 13px 12px;
        border-bottom: 1px solid #edf0f2;
    }

    .product-image {
        width: 52px;
        height: 52px;
        object-fit: contain;
        border: 1px solid #e5e7eb;
        border-radius: 7px;
        background: #fff;
    }

    .product-image-placeholder {
        width: 52px;
        height: 52px;
        border: 1px solid #e5e7eb;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
        background: #f8f9fa;
    }

    .product-title {
        font-weight: 600;
        color: #212529;
        max-width: 330px;
    }

    .product-title small {
        display: block;
        color: #8a9299;
        font-weight: 400;
        margin-top: 3px;
    }

    .sku {
        font-family: monospace;
        font-size: 13px;
        color: #495057;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 20px;
        padding: 5px 9px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .availability-in-stock {
        background: #e8f7ee;
        color: #198754;
    }

    .availability-backordered {
        background: #fff4d6;
        color: #9a6700;
    }

    .availability-out {
        background: #fdecec;
        color: #dc3545;
    }

    .availability-default {
        background: #f1f3f5;
        color: #6c757d;
    }

    .shopify-exists {
        background: #e8f7ee;
        color: #198754;
    }

    .shopify-not-found {
        background: #fff4d6;
        color: #9a6700;
    }

    .shopify-error {
        background: #fdecec;
        color: #dc3545;
    }

    .shopify-not-checked,
    .shopify-default {
        background: #f1f3f5;
        color: #6c757d;
    }

    .shopify-status-text {
        font-size: 11px;
        color: #8a9299;
        margin-top: 3px;
    }

    .updated-text {
        font-size: 12px;
        color: #6c757d;
        white-space: nowrap;
    }

    .action-cell {
        min-width: 130px;
    }

    .push-btn {
        min-width: 118px;
    }

    .push-btn .spinner-border {
        display: none;
    }

    .push-btn.loading .button-text {
        display: none;
    }

    .push-btn.loading .spinner-border {
        display: inline-block;
    }

    .push-btn.loading {
        pointer-events: none;
    }

    .bulk-btn .spinner-border {
        display: none;
    }

    .bulk-btn.loading .button-text {
        display: none;
    }

    .bulk-btn.loading .spinner-border {
        display: inline-block;
    }

    .bulk-btn.loading {
        pointer-events: none;
    }

    .empty-state {
        padding: 60px 20px;
        text-align: center;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 38px;
        margin-bottom: 12px;
    }

    .products-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 18px;
        gap: 15px;
    }

    .results-count {
        color: #6c757d;
        font-size: 13px;
    }

    .sync-result {
        display: none;
        margin-bottom: 15px;
    }

    .sync-result.show {
        display: block;
    }

    .bulk-progress {
        display: none;
        margin-bottom: 15px;
    }

    .bulk-progress.show {
        display: block;
    }

    .bulk-progress-text {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        margin-bottom: 6px;
    }

    @media (max-width: 991px) {
        .products-header {
            flex-direction: column;
        }

        .sync-info {
            text-align: left;
        }

        .products-table {
            min-width: 1050px;
        }

        .products-card {
            overflow-x: auto;
        }

        .products-footer {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="products-page">

    {{-- ============================================================
         HEADER
    ============================================================= --}}

    <div class="products-header">

        <div>
            <h1>Products</h1>

            <p>
                Browse Fullscript products and push them to Shopify.
                Existing products will be updated, new products will be created.
            </p>
        </div>

        <div class="sync-info">

            <a
                href="{{ route('products.index', request()->except('page')) }}"
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="bi bi-arrow-clockwise me-1"></i>
                Sync Latest Products
            </a>

            @if($lastSyncedAt)
                <small>
                    Last synced:
                    {{ $lastSyncedAt }}
                </small>
            @else
                <small>
                    Showing current Fullscript catalog data
                </small>
            @endif

        </div>

    </div>


    {{-- ============================================================
         ERROR
    ============================================================= --}}

    @if(!empty($error))

        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>

            {{ $error }}
        </div>

    @endif


    {{-- ============================================================
         AJAX RESULT
    ============================================================= --}}

    <div
        id="syncResult"
        class="sync-result"
    ></div>


    {{-- ============================================================
         FILTERS
    ============================================================= --}}

    <div class="filter-card">

        <form
            method="GET"
            action="{{ route('products.index') }}"
        >

            <div class="row g-3 align-items-end">

                {{-- Brand --}}

                <div class="col-md-4">

                    <label
                        for="brand"
                        class="filter-label"
                    >
                        Brand / Vendor
                    </label>

                    <select
                        name="brand"
                        id="brand"
                        class="form-select"
                    >

                        <option value="">
                            All Brands
                        </option>

                        @foreach($brands as $brandOption)

                            @if($brandOption !== 'All Brands')

                                <option
                                    value="{{ $brandOption }}"
                                    @selected($brand === $brandOption)
                                >
                                    {{ $brandOption }}
                                </option>

                            @endif

                        @endforeach

                    </select>

                </div>


                {{-- Search --}}

                <div class="col-md-5">

                    <label
                        for="search"
                        class="filter-label"
                    >
                        Product Name / SKU
                    </label>

                    <input
                        type="text"
                        name="search"
                        id="search"
                        class="form-control"
                        value="{{ $search }}"
                        placeholder="Search product name or SKU..."
                    >

                </div>


                {{-- Per page --}}

                <div class="col-md-2">

                    <label
                        for="per_page"
                        class="filter-label"
                    >
                        Show per page
                    </label>

                    <select
                        name="per_page"
                        id="per_page"
                        class="form-select"
                    >

                        <option
                            value="25"
                            @selected($perPage == 25)
                        >
                            25
                        </option>

                        <option
                            value="50"
                            @selected($perPage == 50)
                        >
                            50
                        </option>

                        <option
                            value="100"
                            @selected($perPage == 100)
                        >
                            100
                        </option>

                    </select>

                </div>


                {{-- Submit --}}

                <div class="col-md-1">

                    <button
                        type="submit"
                        class="btn btn-dark w-100"
                        title="Apply filters"
                    >
                        <i class="bi bi-search"></i>
                    </button>

                </div>

            </div>


            {{-- ====================================================
                 QUICK BRAND FILTERS
            ===================================================== --}}

            <div class="brand-pills">

                @foreach($brands as $brandOption)

                    @if($brandOption === 'All Brands')

                        <a
                            href="{{ route('products.index', [
                                'per_page' => $perPage,
                            ]) }}"
                            class="brand-pill {{ $brand === '' ? 'active' : '' }}"
                        >
                            All Brands
                        </a>

                    @else

                        <a
                            href="{{ route('products.index', [
                                'brand' => $brandOption,
                                'per_page' => $perPage,
                            ]) }}"
                            class="brand-pill {{ $brand === $brandOption ? 'active' : '' }}"
                        >
                            {{ $brandOption }}
                        </a>

                    @endif

                @endforeach

            </div>

        </form>

    </div>


    {{-- ============================================================
         SELECTION BAR
    ============================================================= --}}

    <div
        id="selectionBar"
        class="selection-bar"
    >

        <div class="selection-count">

            <i class="bi bi-check2-square me-1"></i>

            <span id="selectedCount">
                0
            </span>

            products selected

            <span class="text-muted">
                (of {{ $products->count() }} on this page)
            </span>

        </div>

        <div class="d-flex gap-2">

            <button
                type="button"
                id="pushSelectedBtn"
                class="btn btn-dark btn-sm bulk-btn"
            >

                <span class="spinner-border spinner-border-sm me-1"></span>

                <span class="button-text">
                    <i class="bi bi-cloud-arrow-up me-1"></i>
                    Push Selected to Shopify
                    (<span id="selectedCountButton">0</span>)
                </span>

            </button>

            <button
                type="button"
                id="clearSelectionBtn"
                class="btn btn-outline-secondary btn-sm"
            >
                Clear Selection
            </button>

        </div>

    </div>


    {{-- ============================================================
         BULK PROGRESS
    ============================================================= --}}

    <div
        id="bulkProgress"
        class="bulk-progress"
    >

        <div class="bulk-progress-text">

            <span id="bulkProgressText">
                Processing products...
            </span>

            <span id="bulkProgressPercent">
                0%
            </span>

        </div>

        <div class="progress">
            <div
                id="bulkProgressBar"
                class="progress-bar"
                role="progressbar"
                style="width: 0%"
            ></div>
        </div>

    </div>


    {{-- ============================================================
         PRODUCTS TABLE
    ============================================================= --}}

    <div class="products-card">

        @if($products->count())

            <table class="table products-table">

                <thead>

                    <tr>

                        {{-- Select all --}}

                        <th style="width: 42px;">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="selectAll"
                                title="Select all products on this page"
                            >

                        </th>


                        {{-- Image --}}

                        <th>
                            Image
                        </th>


                        {{-- Product --}}

                        <th>
                            Product
                        </th>


                        {{-- Brand --}}

                        <th>
                            Brand
                        </th>


                        {{-- SKU --}}

                        <th>
                            SKU
                        </th>


                        {{-- Availability --}}

                        <th>
                            Availability
                        </th>


                        {{-- Shopify --}}

                        <th>
                            Shopify Status
                        </th>


                        {{-- Updated --}}

                        <th>
                            Updated
                        </th>


                        {{-- Action --}}

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach($products as $product)

                        @php

                            $productId =
                                $product['id'] ?? null;

                            $shopifyStatus =
                                $product['shopify_status']
                                ?? 'Not Checked';

                            $availability =
                                $product['availability']
                                ?? 'Unknown';

                            $availabilityClass =
                                match(
                                    strtolower(
                                        trim(
                                            $availability
                                        )
                                    )
                                ) {

                                    'in stock' =>
                                        'availability-in-stock',

                                    'backordered' =>
                                        'availability-backordered',

                                    'out of stock' =>
                                        'availability-out',

                                    default =>
                                        'availability-default',
                                };

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


                        <tr
                            data-product-id="{{ $productId }}"
                            data-product-sku="{{ $product['sku'] ?? '' }}"
                        >

                            {{-- =================================================
                                 CHECKBOX
                            ================================================== --}}

                            <td>

                                @if($productId)

                                    <input
                                        type="checkbox"
                                        class="form-check-input product-checkbox"
                                        value="{{ $productId }}"
                                    >

                                @endif

                            </td>


                            {{-- =================================================
                                 IMAGE
                            ================================================== --}}

                            <td>

                                @if(!empty($product['image']))

                                    <img
                                        src="{{ $product['image'] }}"
                                        alt="{{ $product['title'] ?? 'Product' }}"
                                        class="product-image"
                                        loading="lazy"
                                    >

                                @else

                                    <div class="product-image-placeholder">

                                        <i class="bi bi-image"></i>

                                    </div>

                                @endif

                            </td>


                            {{-- =================================================
                                 PRODUCT
                            ================================================== --}}

                            <td>

                                <div class="product-title">

                                    {{ $product['title'] ?? 'Untitled Product' }}

                                    @if(!empty($product['id']))

                                        <small>
                                            Fullscript ID:
                                            {{ $product['id'] }}
                                        </small>

                                    @endif

                                </div>

                            </td>


                            {{-- =================================================
                                 BRAND
                            ================================================== --}}

                            <td>

                                {{ $product['brand'] ?? '—' }}

                            </td>


                            {{-- =================================================
                                 SKU
                            ================================================== --}}

                            <td>

                                @if(!empty($product['sku']))

                                    <span class="sku">
                                        {{ $product['sku'] }}
                                    </span>

                                @else

                                    <span class="text-muted">
                                        No SKU
                                    </span>

                                @endif

                            </td>


                            {{-- =================================================
                                 AVAILABILITY
                            ================================================== --}}

                            <td>

                                <span
                                    class="status-badge {{ $availabilityClass }}"
                                >

                                    {{ $availability }}

                                </span>

                            </td>


                            {{-- =================================================
                                 SHOPIFY STATUS
                            ================================================== --}}

                            <td>

                                <div>

                                    <span
                                        class="status-badge {{ $shopifyStatusClass }}"
                                    >

                                        @if($shopifyStatus === 'Exists')

                                            <i class="bi bi-check2-circle me-1"></i>

                                        @elseif($shopifyStatus === 'Error')

                                            <i class="bi bi-exclamation-circle me-1"></i>

                                        @elseif($shopifyStatus === 'Not Found')

                                            <i class="bi bi-dash-circle me-1"></i>

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

                                    <span class="updated-text">

                                        {{ $product['updated_at'] }}

                                    </span>

                                @else

                                    <span class="text-muted">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- =================================================
                                 ACTION
                            ================================================== --}}

                            <td class="action-cell">

                                @if($productId)

                                    <button
                                        type="button"
                                        class="btn btn-sm {{ $shopifyStatus === 'Exists' ? 'btn-outline-primary' : 'btn-dark' }} push-btn"
                                        data-product-id="{{ $productId }}"
                                        data-product-title="{{ $product['title'] ?? 'Product' }}"
                                    >

                                        <span class="spinner-border spinner-border-sm me-1"></span>

                                        <span class="button-text">

                                            @if($shopifyStatus === 'Exists')

                                                <i class="bi bi-arrow-repeat me-1"></i>
                                                Update Shopify

                                            @elseif($shopifyStatus === 'Error')

                                                <i class="bi bi-arrow-clockwise me-1"></i>
                                                Retry

                                            @else

                                                <i class="bi bi-cloud-arrow-up me-1"></i>
                                                Push to Shopify

                                            @endif

                                        </span>

                                    </button>

                                @else

                                    <span class="text-muted">
                                        No ID
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        @else

            <div class="empty-state">

                <i class="bi bi-box-seam d-block"></i>

                <h5>
                    No products found
                </h5>

                <p class="mb-0">
                    Try changing your brand or search filters.
                </p>

            </div>

        @endif


        {{-- ============================================================
             FOOTER / PAGINATION
        ============================================================= --}}

        <div class="products-footer">

            <div class="results-count">

                @if($products->total() > 0)

                    Showing

                    <strong>
                        {{ $products->firstItem() }}
                    </strong>

                    to

                    <strong>
                        {{ $products->lastItem() }}
                    </strong>

                    of

                    <strong>
                        {{ number_format($products->total()) }}
                    </strong>

                    products

                @else

                    No products

                @endif

            </div>


            <div>

                {{ $products->onEachSide(1)->links() }}

            </div>

        </div>

    </div>

</div>


{{-- ================================================================
     JAVASCRIPT
================================================================= --}}

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Elements
        |--------------------------------------------------------------------------
        */

        const selectAll =
            document.getElementById(
                'selectAll'
            );

        const checkboxes =
            Array.from(
                document.querySelectorAll(
                    '.product-checkbox'
                )
            );

        const selectionBar =
            document.getElementById(
                'selectionBar'
            );

        const selectedCount =
            document.getElementById(
                'selectedCount'
            );

        const selectedCountButton =
            document.getElementById(
                'selectedCountButton'
            );

        const clearSelectionBtn =
            document.getElementById(
                'clearSelectionBtn'
            );

        const pushSelectedBtn =
            document.getElementById(
                'pushSelectedBtn'
            );

        const syncResult =
            document.getElementById(
                'syncResult'
            );

        const bulkProgress =
            document.getElementById(
                'bulkProgress'
            );

        const bulkProgressText =
            document.getElementById(
                'bulkProgressText'
            );

        const bulkProgressPercent =
            document.getElementById(
                'bulkProgressPercent'
            );

        const bulkProgressBar =
            document.getElementById(
                'bulkProgressBar'
            );


        /*
        |--------------------------------------------------------------------------
        | CSRF
        |--------------------------------------------------------------------------
        */

        const csrfToken =
            document.querySelector(
                'meta[name="csrf-token"]'
            )?.getAttribute(
                'content'
            );


        /*
        |--------------------------------------------------------------------------
        | Get selected product IDs
        |--------------------------------------------------------------------------
        */

        function getSelectedProductIds() {

            return checkboxes
                .filter(
                    checkbox =>
                        checkbox.checked
                )
                .map(
                    checkbox =>
                        checkbox.value
                );

        }


        /*
        |--------------------------------------------------------------------------
        | Update selection UI
        |--------------------------------------------------------------------------
        */

        function updateSelectionUI() {

            const selected =
                getSelectedProductIds();

            const count =
                selected.length;

            selectedCount.textContent =
                count;

            selectedCountButton.textContent =
                count;

            if (count > 0) {

                selectionBar.classList.add(
                    'show'
                );

            } else {

                selectionBar.classList.remove(
                    'show'
                );

            }


            /*
            |--------------------------------------------------------------
            | Update select-all state
            |--------------------------------------------------------------
            */

            if (selectAll) {

                if (
                    count > 0 &&
                    count === checkboxes.length
                ) {

                    selectAll.checked = true;

                    selectAll.indeterminate =
                        false;

                } else if (count > 0) {

                    selectAll.checked = false;

                    selectAll.indeterminate =
                        true;

                } else {

                    selectAll.checked = false;

                    selectAll.indeterminate =
                        false;

                }

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

                    checkboxes.forEach(
                        checkbox => {

                            checkbox.checked =
                                selectAll.checked;

                        }
                    );

                    updateSelectionUI();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Individual checkbox
        |--------------------------------------------------------------------------
        */

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

        if (clearSelectionBtn) {

            clearSelectionBtn.addEventListener(
                'click',
                function () {

                    checkboxes.forEach(
                        checkbox => {

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

                    updateSelectionUI();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Show result
        |--------------------------------------------------------------------------
        */

        function showResult(
            type,
            message
        ) {

            syncResult.className =
                'sync-result alert alert-' +
                type +
                ' show';

            syncResult.innerHTML =
                message;

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

        }


        /*
        |--------------------------------------------------------------------------
        | Individual Push / Update
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.push-btn'
            )
            .forEach(
                button => {

                    button.addEventListener(
                        'click',
                        async function () {

                            const productId =
                                button.dataset.productId;

                            const productTitle =
                                button.dataset.productTitle
                                || 'Product';

                            if (!productId) {
                                return;
                            }


                            /*
                            |--------------------------------------------------
                            | Confirm
                            |--------------------------------------------------
                            */

                            const confirmed =
                                window.confirm(
                                    'Push "' +
                                    productTitle +
                                    '" to Shopify?'
                                );

                            if (!confirmed) {
                                return;
                            }


                            /*
                            |--------------------------------------------------
                            | Loading
                            |--------------------------------------------------
                            */

                            button.classList.add(
                                'loading'
                            );

                            button.disabled =
                                true;


                            try {

                                const url =
                                    "{{ route('products.push', ['productId' => '__PRODUCT_ID__']) }}"
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
                                                    csrfToken,

                                                'X-Requested-With':
                                                    'XMLHttpRequest',
                                            },

                                            body:
                                                JSON.stringify({})
                                        }
                                    );


                                const data =
                                    await response.json();


                                if (!response.ok || !data.success) {

                                    throw new Error(
                                        data.message
                                        || 'Unable to sync product.'
                                    );

                                }


                                /*
                                |----------------------------------------------
                                | Success
                                |----------------------------------------------
                                */

                                showResult(
                                    'success',
                                    '<i class="bi bi-check-circle me-1"></i>' +
                                    escapeHtml(
                                        data.message
                                        || 'Product synced successfully.'
                                    )
                                );


                                /*
                                |----------------------------------------------
                                | Reload after success
                                |----------------------------------------------
                                */

                                setTimeout(
                                    function () {

                                        window.location.reload();

                                    },
                                    1000
                                );


                            } catch (error) {

                                showResult(
                                    'danger',
                                    '<i class="bi bi-exclamation-triangle me-1"></i>' +
                                    escapeHtml(
                                        error.message
                                        || 'Product sync failed.'
                                    )
                                );


                                button.classList.remove(
                                    'loading'
                                );

                                button.disabled =
                                    false;

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

        if (pushSelectedBtn) {

            pushSelectedBtn.addEventListener(
                'click',
                async function () {

                    const productIds =
                        getSelectedProductIds();


                    if (!productIds.length) {

                        showResult(
                            'warning',
                            'Please select at least one product.'
                        );

                        return;

                    }


                    /*
                    |--------------------------------------------------------------
                    | Confirm
                    |--------------------------------------------------------------
                    */

                    const confirmed =
                        window.confirm(
                            'Push ' +
                            productIds.length +
                            ' selected product(s) to Shopify?'
                        );

                    if (!confirmed) {
                        return;
                    }


                    /*
                    |--------------------------------------------------------------
                    | Loading
                    |--------------------------------------------------------------
                    */

                    pushSelectedBtn.classList.add(
                        'loading'
                    );

                    pushSelectedBtn.disabled =
                        true;

                    bulkProgress.classList.add(
                        'show'
                    );

                    bulkProgressBar.style.width =
                        '10%';

                    bulkProgressPercent.textContent =
                        'Processing...';

                    bulkProgressText.textContent =
                        'Syncing ' +
                        productIds.length +
                        ' product(s)...';


                    try {

                        const url =
                            "{{ route('products.push-selected') }}";


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
                                            csrfToken,

                                        'X-Requested-With':
                                            'XMLHttpRequest',
                                    },

                                    body:
                                        JSON.stringify({
                                            product_ids:
                                                productIds
                                        })
                                }
                            );


                        const data =
                            await response.json();


                        if (
                            !response.ok &&
                            response.status !== 207
                        ) {

                            throw new Error(
                                data.message
                                || 'Bulk sync failed.'
                            );

                        }


                        /*
                        |----------------------------------------------------------
                        | Summary
                        |----------------------------------------------------------
                        */

                        const summary =
                            data.summary
                            || {};

                        const total =
                            summary.total
                            || productIds.length;

                        const created =
                            summary.created
                            || 0;

                        const updated =
                            summary.updated
                            || 0;

                        const failed =
                            summary.failed
                            || 0;


                        bulkProgressBar.style.width =
                            '100%';

                        bulkProgressPercent.textContent =
                            '100%';

                        bulkProgressText.textContent =
                            'Sync completed';


                        /*
                        |----------------------------------------------------------
                        | Message
                        |----------------------------------------------------------
                        */

                        let alertType =
                            failed > 0
                                ? 'warning'
                                : 'success';


                        let message =
                            '<strong>Sync completed.</strong> ' +
                            total +
                            ' product(s) processed. ' +
                            created +
                            ' created, ' +
                            updated +
                            ' updated, ' +
                            failed +
                            ' failed.';


                        /*
                        |----------------------------------------------------------
                        | Failed products
                        |----------------------------------------------------------
                        */

                        if (
                            failed > 0 &&
                            Array.isArray(
                                data.results
                            )
                        ) {

                            const failedResults =
                                data.results.filter(
                                    result =>
                                        !result.success
                                );


                            if (
                                failedResults.length
                            ) {

                                message +=
                                    '<hr class="my-2">';

                                message +=
                                    '<strong>Failed products:</strong>';

                                message +=
                                    '<ul class="mb-0 mt-1">';

                                failedResults.forEach(
                                    result => {

                                        message +=
                                            '<li>' +
                                            escapeHtml(
                                                result.message
                                                || 'Unknown error'
                                            ) +
                                            '</li>';

                                    }
                                );

                                message +=
                                    '</ul>';

                            }

                        }


                        showResult(
                            alertType,
                            message
                        );


                        /*
                        |----------------------------------------------------------
                        | Reload after short delay
                        |----------------------------------------------------------
                        */

                        setTimeout(
                            function () {

                                window.location.reload();

                            },
                            1800
                        );


                    } catch (error) {

                        bulkProgressBar.style.width =
                            '100%';

                        bulkProgressPercent.textContent =
                            'Failed';

                        bulkProgressText.textContent =
                            'Bulk sync failed';


                        showResult(
                            'danger',
                            '<i class="bi bi-exclamation-triangle me-1"></i>' +
                            escapeHtml(
                                error.message
                                || 'Bulk sync failed.'
                            )
                        );


                        pushSelectedBtn.classList.remove(
                            'loading'
                        );

                        pushSelectedBtn.disabled =
                            false;

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Escape HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(
            value
        ) {

            const div =
                document.createElement(
                    'div'
                );

            div.textContent =
                value ?? '';

            return div.innerHTML;

        }


        /*
        |--------------------------------------------------------------------------
        | Initial state
        |--------------------------------------------------------------------------
        */

        updateSelectionUI();

    }
);

</script>

@endsection