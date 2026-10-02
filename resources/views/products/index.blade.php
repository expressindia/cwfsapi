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

            <div class="last-synced">

                Last synced:
                {{ $lastSyncedAt }}

            </div>

        </div>

    </div>


    {{-- ================================================================
         SEARCH / FILTER CARD
    ================================================================= --}}

    <div class="card products-filter-card">

        <div class="card-body">

            <div class="row g-3 align-items-end">

                {{-- Brand --}}
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
                            class="form-control products-search-input"
                            placeholder="Search brand..."
                            value="Designs for Health">

                        <button
                            type="button"
                            class="btn btn-primary">

                            <i class="bi bi-search me-1"></i>

                            Search

                        </button>

                    </div>

                </div>


                {{-- Product / SKU --}}
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
                            class="form-control products-search-input"
                            placeholder="Enter product name or SKU">

                        <button
                            type="button"
                            class="btn btn-light products-search-icon">

                            <i class="bi bi-search"></i>

                        </button>

                    </div>

                </div>


                {{-- Per Page --}}
                <div class="col-lg-3">

                    <label
                        for="perPage"
                        class="form-label products-form-label">

                        Show per page

                    </label>

                    <select
                        id="perPage"
                        class="form-select products-search-input">

                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>

                    </select>

                </div>

            </div>


            {{-- Quick Filters --}}

            <div class="quick-filters">

                <span class="quick-filter-label">
                    Quick Filters:
                </span>

                @foreach($brands as $brand)

                    <button
                        type="button"
                        class="brand-chip {{ $loop->first ? 'active' : '' }}">

                        {{ $brand }}

                    </button>

                @endforeach

            </div>

        </div>

    </div>


    {{-- ================================================================
         BULK ACTION BAR
    ================================================================= --}}

    <div
        class="bulk-action-bar"
        id="bulkActionBar">

        <div class="bulk-selection">

            <div class="bulk-check-icon">

                <i class="bi bi-check-lg"></i>

            </div>

            <strong id="selectedCount">
                {{ $selectedCount }}
            </strong>

            products selected

            <span class="bulk-page-count">
                (of {{ $perPage }} on this page)
            </span>

        </div>


        <div class="bulk-actions">

            <button
                type="button"
                class="btn btn-primary"
                id="pushSelectedButton">

                <i class="bi bi-cloud-arrow-up me-2"></i>

                Push Selected to Shopify
                (<span id="selectedButtonCount">{{ $selectedCount }}</span>)

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

                        <th class="checkbox-column">

                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="selectAll">

                        </th>

                        <th class="image-column">
                            Image
                        </th>

                        <th>
                            Product
                            <i class="bi bi-chevron-expand sort-icon"></i>
                        </th>

                        <th>
                            Brand
                            <i class="bi bi-chevron-expand sort-icon"></i>
                        </th>

                        <th>
                            SKU
                            <i class="bi bi-chevron-expand sort-icon"></i>
                        </th>

                        <th>
                            Availability
                            <i class="bi bi-chevron-expand sort-icon"></i>
                        </th>

                        <th>
                            Shopify Status
                            <i class="bi bi-chevron-expand sort-icon"></i>
                        </th>

                        <th>
                            Updated
                            <i class="bi bi-chevron-expand sort-icon"></i>
                        </th>

                        <th class="action-column">
                            Action
                        </th>

                        <th class="menu-column"></th>

                    </tr>

                </thead>


                <tbody>

                    @foreach($products as $product)

                        <tr>

                            {{-- Checkbox --}}
                            <td>

                                <input
                                    type="checkbox"
                                    class="form-check-input product-checkbox"
                                    value="{{ $product['id'] }}"
                                    {{ in_array($loop->index, [0, 1, 3]) ? 'checked' : '' }}>

                            </td>


                            {{-- Image --}}
                            <td>

                                <div class="product-image">

                                    @if(!empty($product['image']))

                                        <img
                                            src="{{ $product['image'] }}"
                                            alt="{{ $product['title'] }}">

                                    @else

                                        <div class="product-image-placeholder">

                                            <i class="bi bi-box-seam"></i>

                                        </div>

                                    @endif

                                </div>

                            </td>


                            {{-- Product --}}
                            <td>

                                <a
                                    href="#"
                                    class="product-name">

                                    {{ $product['title'] }}

                                </a>

                            </td>


                            {{-- Brand --}}
                            <td>

                                <span class="brand-name">

                                    {{ $product['brand'] }}

                                </span>

                            </td>


                            {{-- SKU --}}
                            <td>

                                <span class="sku-text">

                                    {{ $product['sku'] }}

                                </span>

                            </td>


                            {{-- Availability --}}
                            <td>

                                @php
                                    $availabilityClass = match(
                                        strtolower($product['availability'])
                                    ) {
                                        'in stock' => 'availability-in-stock',
                                        'backordered' => 'availability-backordered',
                                        'out of stock' => 'availability-out-of-stock',
                                        default => 'availability-default',
                                    };
                                @endphp

                                <span
                                    class="status-badge {{ $availabilityClass }}">

                                    {{ $product['availability'] }}

                                </span>

                            </td>


                            {{-- Shopify Status --}}
                            <td>

                                @php
                                    $shopifyClass = match(
                                        strtolower($product['shopify_status'])
                                    ) {
                                        'exists' => 'shopify-exists',
                                        'not found' => 'shopify-not-found',
                                        'error' => 'shopify-error',
                                        default => 'shopify-default',
                                    };
                                @endphp

                                <div>

                                    <span
                                        class="status-badge {{ $shopifyClass }}">

                                        @if($product['shopify_status'] === 'Exists')

                                            <i class="bi bi-check2-circle me-1"></i>

                                        @elseif($product['shopify_status'] === 'Error')

                                            <i class="bi bi-exclamation-circle me-1"></i>

                                        @endif

                                        {{ $product['shopify_status'] }}

                                    </span>

                                    <div class="shopify-status-text">

                                        {{ $product['shopify_status_text'] }}

                                    </div>

                                </div>

                            </td>


                            {{-- Updated --}}
                            <td>

                                @if($product['updated_at'])

                                    <span class="updated-text">

                                        {{ \Illuminate\Support\Str::before(
                                            $product['updated_at'],
                                            ' '
                                        ) }}

                                    </span>

                                    <br>

                                    <span class="updated-time">

                                        {{ \Illuminate\Support\Str::after(
                                            $product['updated_at'],
                                            ' '
                                        ) }}

                                    </span>

                                @else

                                    <span class="updated-empty">
                                        —
                                    </span>

                                @endif

                            </td>


                            {{-- Action --}}
                            <td>

                                @switch($product['action'])

                                    @case('push')

                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm product-action-button">

                                            <i class="bi bi-cloud-arrow-up me-1"></i>

                                            Push to Shopify

                                        </button>

                                        @break


                                    @case('update')

                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm product-action-button">

                                            <i class="bi bi-arrow-repeat me-1"></i>

                                            Update in Shopify

                                        </button>

                                        @break


                                    @case('view')

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm product-action-button">

                                            <i class="bi bi-eye me-1"></i>

                                            View in Shopify

                                        </button>

                                        @break


                                    @case('retry')

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm product-action-button">

                                            <i class="bi bi-arrow-repeat me-1"></i>

                                            Retry Sync

                                        </button>

                                        @break

                                @endswitch

                            </td>


                            {{-- Menu --}}
                            <td>

                                <button
                                    type="button"
                                    class="btn btn-sm product-menu-button">

                                    <i class="bi bi-three-dots-vertical"></i>

                                </button>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </div>


    {{-- ================================================================
         PAGINATION
    ================================================================= --}}

    <div class="products-pagination">

        <div class="pagination-summary">

            Showing
            <strong>1</strong>
            to
            <strong>25</strong>
            of
            <strong>{{ number_format($totalProducts) }}</strong>
            products

        </div>


        <nav>

            <ul class="pagination mb-0">

                <li class="page-item disabled">

                    <a class="page-link" href="#">
                        <i class="bi bi-chevron-left"></i>
                        Previous
                    </a>

                </li>

                <li class="page-item active">

                    <a class="page-link" href="#">
                        1
                    </a>

                </li>

                <li class="page-item">

                    <a class="page-link" href="#">
                        2
                    </a>

                </li>

                <li class="page-item">

                    <a class="page-link" href="#">
                        3
                    </a>

                </li>

                <li class="page-item">

                    <a class="page-link" href="#">
                        4
                    </a>

                </li>

                <li class="page-item">

                    <a class="page-link" href="#">
                        5
                    </a>

                </li>

                <li class="page-item disabled">

                    <span class="page-link">
                        ...
                    </span>

                </li>

                <li class="page-item">

                    <a class="page-link" href="#">
                        50
                    </a>

                </li>

                <li class="page-item">

                    <a class="page-link" href="#">

                        Next

                        <i class="bi bi-chevron-right ms-1"></i>

                    </a>

                </li>

            </ul>

        </nav>

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
        transition: all .15s ease;
    }

    .brand-chip:hover {
        background: #e9edf2;
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
        display: flex;
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

    .availability-default {
        background: #eef0f2;
        color: #59636e;
    }

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

</style>


{{-- =====================================================================
     PAGE JAVASCRIPT
===================================================================== --}}

@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Product checkboxes
    |--------------------------------------------------------------------------
    */

    const selectAll =
        document.getElementById('selectAll');

    const productCheckboxes =
        document.querySelectorAll('.product-checkbox');

    const selectedCount =
        document.getElementById('selectedCount');

    const selectedButtonCount =
        document.getElementById('selectedButtonCount');

    const clearSelectionButton =
        document.getElementById('clearSelectionButton');


    function updateSelectionCount() {

        const checked =
            document.querySelectorAll(
                '.product-checkbox:checked'
            ).length;

        selectedCount.textContent =
            checked;

        selectedButtonCount.textContent =
            checked;

        if (selectAll) {

            selectAll.checked =
                checked === productCheckboxes.length;

            selectAll.indeterminate =
                checked > 0 &&
                checked < productCheckboxes.length;

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
                    checkbox => {

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
    | Individual selection
    |--------------------------------------------------------------------------
    */

    productCheckboxes.forEach(
        checkbox => {

            checkbox.addEventListener(
                'change',
                updateSelectionCount
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
                    checkbox => {

                        checkbox.checked = false;

                    }
                );

                selectAll.checked = false;

                updateSelectionCount();

            }
        );

    }


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

                syncButton.disabled = true;

                syncButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>' +
                    'Syncing...';

                /*
                 * Step 1 only.
                 *
                 * Actual Fullscript synchronization
                 * will be connected in the next step.
                 */

                setTimeout(
                    function () {

                        syncButton.disabled =
                            false;

                        syncButton.innerHTML =
                            originalHtml;

                    },
                    1200
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Initial selection count
    |--------------------------------------------------------------------------
    */

    updateSelectionCount();

});

</script>

@endpush

@endsection