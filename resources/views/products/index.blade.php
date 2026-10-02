@extends('layouts.app')

@section('title', 'Products - CWFSAPI')

@section('page-title', 'Products')

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
                Search Fullscript products by brand and push them to Shopify.
                Existing products will be updated and new products will be created.
            </p>
        </div>

        <div class="products-header-actions">

            <button
                type="button"
                class="btn btn-primary products-sync-button"
                id="syncLatestProducts"
            >
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
         ERROR
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
         SEARCH CARD
    ================================================================= --}}

    <div class="card products-filter-card">

        <div class="card-body">

            <form
                method="GET"
                action="{{ route('products.index') }}"
                id="productSearchForm"
            >

                <div class="row g-3 align-items-end">

                    {{-- BRAND --}}

                    <div class="col-lg-5">

                        <label
                            for="brandSearch"
                            class="form-label products-form-label"
                        >
                            Brand Name
                        </label>

                        <div class="input-group">

                            <input
                                type="text"
                                id="brandSearch"
                                name="brand"
                                class="form-control products-search-input"
                                placeholder="Enter brand name"
                                value="{{ $brand ?? '' }}"
                                autocomplete="off"
                                required
                            >

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-search me-1"></i>
                                Search
                            </button>

                        </div>

                        <div class="form-text">
                            Enter the Fullscript brand name and search.
                        </div>

                    </div>


                    {{-- PRODUCT / SKU --}}

                    <div class="col-lg-4">

                        <label
                            for="productSearch"
                            class="form-label products-form-label"
                        >
                            Product Name / SKU
                            <span class="text-muted">
                                (optional)
                            </span>
                        </label>

                        <div class="input-group">

                            <input
                                type="text"
                                id="productSearch"
                                name="search"
                                class="form-control products-search-input"
                                placeholder="Enter product name or SKU"
                                value="{{ $search ?? '' }}"
                            >

                            @if(!empty($brand))

                                <button
                                    type="submit"
                                    class="btn btn-light products-search-icon"
                                    title="Search"
                                >
                                    <i class="bi bi-search"></i>
                                </button>

                            @endif

                        </div>

                    </div>


                    {{-- PER PAGE --}}

                    <div class="col-lg-3">

                        <label
                            for="perPage"
                            class="form-label products-form-label"
                        >
                            Show per page
                        </label>

                        <select
                            id="perPage"
                            name="per_page"
                            class="form-select products-search-input"
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

            </form>

        </div>

    </div>


    {{-- ================================================================
         NO BRAND SEARCH
    ================================================================= --}}

    @if(empty($brand))

        <div class="products-empty-state">

            <div class="products-empty-icon">
                <i class="bi bi-search"></i>
            </div>

            <h4>
                Search by brand
            </h4>

            <p>
                Enter a Fullscript brand name above to view its products.
            </p>

        </div>

    @elseif($products->count() === 0)

        {{-- ============================================================
             NO PRODUCTS
        ============================================================= --}}

        <div class="products-empty-state">

            <div class="products-empty-icon">
                <i class="bi bi-box-seam"></i>
            </div>

            <h4>
                No products found
            </h4>

            <p>
                No Fullscript products were found for
                <strong>
                    "{{ $brand }}"
                </strong>.
            </p>

            @if(!empty($search))

                <p class="text-muted mb-0">
                    Try removing the product/SKU search or use a different brand name.
                </p>

            @endif

        </div>

    @else

        {{-- ============================================================
             SELECTION BAR
        ============================================================= --}}

        <div
            class="bulk-action-bar"
            id="bulkActionBar"
            style="display:none;"
        >

            <div>

                <strong id="selectedCount">
                    0
                </strong>

                products selected

                <span class="text-muted">
                    (of {{ $products->count() }} on this page)
                </span>

            </div>

            <div class="bulk-actions">

                <button
                    type="button"
                    class="btn btn-primary"
                    id="pushSelectedButton"
                >
                    <i class="bi bi-cloud-arrow-up me-1"></i>
                    Push Selected to Shopify
                </button>

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    id="clearSelectionButton"
                >
                    Clear Selection
                </button>

            </div>

        </div>


        {{-- ============================================================
             PRODUCTS TABLE
        ============================================================= --}}

        <div class="card products-table-card">

            <div class="table-responsive">

                <table class="table products-table mb-0">

                    <thead>

                        <tr>

                            <th class="checkbox-column">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="selectAllProducts"
                                >
                            </th>

                            <th>
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

                                <td>

                                    <input
                                        type="checkbox"
                                        class="form-check-input product-checkbox"
                                        value="{{ $product['id'] }}"
                                        data-product-id="{{ $product['id'] }}"
                                    >

                                </td>


                                {{-- IMAGE --}}

                                <td>

                                    @if(!empty($product['image']))

                                        <img
                                            src="{{ $product['image'] }}"
                                            alt="{{ $product['title'] }}"
                                            class="product-thumbnail"
                                        >

                                    @else

                                        <div class="product-thumbnail-placeholder">
                                            <i class="bi bi-image"></i>
                                        </div>

                                    @endif

                                </td>


                                {{-- PRODUCT --}}

                                <td>

                                    <div class="product-name">
                                        {{ $product['title'] }}
                                    </div>

                                </td>


                                {{-- BRAND --}}

                                <td>

                                    <span class="brand-name">
                                        {{ $product['brand'] ?: '—' }}
                                    </span>

                                </td>


                                {{-- SKU --}}

                                <td>

                                    <code>
                                        {{ $product['sku'] ?: '—' }}
                                    </code>

                                </td>


                                {{-- AVAILABILITY --}}

                                <td>

                                    @php
                                        $availability =
                                            strtolower(
                                                $product['availability'] ?? ''
                                            );
                                    @endphp

                                    @if($availability === 'in stock')

                                        <span class="status-badge status-success">
                                            In Stock
                                        </span>

                                    @elseif($availability === 'backordered')

                                        <span class="status-badge status-warning">
                                            Backordered
                                        </span>

                                    @elseif($availability === 'out of stock')

                                        <span class="status-badge status-danger">
                                            Out of Stock
                                        </span>

                                    @else

                                        <span class="status-badge status-secondary">
                                            {{ $product['availability'] ?: 'Unknown' }}
                                        </span>

                                    @endif

                                </td>


                                {{-- SHOPIFY STATUS --}}

                                <td>

                                    @if(($product['shopify_status'] ?? '') === 'Exists')

                                        <span class="status-badge status-success">
                                            Exists
                                        </span>

                                        <div class="small text-muted mt-1">
                                            Will update
                                        </div>

                                    @elseif(($product['shopify_status'] ?? '') === 'Not Found')

                                        <span class="status-badge status-info">
                                            Not Found
                                        </span>

                                        <div class="small text-muted mt-1">
                                            Will create
                                        </div>

                                    @else

                                        <span class="status-badge status-danger">
                                            Error
                                        </span>

                                        <div class="small text-muted mt-1">
                                            {{ $product['shopify_status_text'] ?? 'Unable to check' }}
                                        </div>

                                    @endif

                                </td>


                                {{-- UPDATED --}}

                                <td>

                                    <span class="small text-muted">
                                        {{ $product['updated_at'] ?? '—' }}
                                    </span>

                                </td>


                                {{-- ACTION --}}

                                <td>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary push-product-button"
                                        data-product-id="{{ $product['id'] }}"
                                    >

                                        <i class="bi bi-cloud-arrow-up me-1"></i>

                                        @if(($product['action'] ?? 'push') === 'update')
                                            Update
                                        @else
                                            Push
                                        @endif

                                    </button>

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

        <div class="products-pagination">

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

                <div>

                    {{ $products->onEachSide(1)->links('pagination::bootstrap-5') }}

                </div>

            @endif

        </div>

    @endif

</div>


{{-- =====================================================================
     STYLES
===================================================================== --}}

<style>

.products-page {
    width: 100%;
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
    font-weight: 600;
    color: #1f2937;
}

.products-subtitle {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 15px;
}

.products-header-actions {
    text-align: right;
}

.products-sync-button {
    min-width: 210px;
    height: 40px;
    border-radius: 6px;
}

.last-synced {
    margin-top: 7px;
    color: #6b7280;
    font-size: 13px;
}

.products-alert {
    border-radius: 7px;
    margin-bottom: 18px;
}

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

.products-search-input {
    min-height: 40px;
}

.products-empty-state {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 55px 20px;
    text-align: center;
}

.products-empty-icon {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 25px;
}

.products-empty-state h4 {
    margin-bottom: 8px;
    font-size: 18px;
}

.products-empty-state p {
    margin: 0;
    color: #6b7280;
}

.bulk-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 7px;
    padding: 12px 15px;
    margin-bottom: 12px;
}

.bulk-actions {
    display: flex;
    gap: 8px;
}

.products-table-card {
    border: 1px solid #e5e7eb;
    border-radius: 7px;
    overflow: hidden;
}

.products-table {
    min-width: 1100px;
}

.products-table thead th {
    background: #f8f9fa;
    color: #4b5563;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    padding: 13px 12px;
    white-space: nowrap;
}

.products-table tbody td {
    padding: 13px 12px;
    vertical-align: middle;
    border-color: #f0f1f2;
}

.checkbox-column {
    width: 42px;
}

.product-thumbnail,
.product-thumbnail-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    object-fit: contain;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.product-thumbnail-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    font-size: 18px;
}

.product-name {
    font-weight: 600;
    color: #1f2937;
    max-width: 330px;
}

.brand-name {
    color: #4b5563;
}

.products-table code {
    font-size: 12px;
    color: #374151;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-info {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-warning {
    background: #fef3c7;
    color: #92400e;
}

.status-danger {
    background: #fee2e2;
    color: #991b1b;
}

.status-secondary {
    background: #f3f4f6;
    color: #4b5563;
}

.products-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 18px;
}

.pagination-summary {
    color: #6b7280;
    font-size: 13px;
}

.products-pagination .pagination {
    margin-bottom: 0;
}

.products-pagination .page-link {
    min-width: 34px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}

@media (max-width: 992px) {

    .products-header {
        flex-direction: column;
        gap: 15px;
    }

    .products-header-actions {
        width: 100%;
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

    .products-sync-button {
        width: 100%;
    }

    .products-header-actions {
        width: 100%;
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

        const selectAll =
            document.getElementById(
                'selectAllProducts'
            );

        const checkboxes =
            () => Array.from(
                document.querySelectorAll(
                    '.product-checkbox'
                )
            );

        const bulkBar =
            document.getElementById(
                'bulkActionBar'
            );

        const selectedCount =
            document.getElementById(
                'selectedCount'
            );

        const clearButton =
            document.getElementById(
                'clearSelectionButton'
            );

        const pushSelectedButton =
            document.getElementById(
                'pushSelectedButton'
            );


        function updateSelectionUI() {

            const selected =
                checkboxes().filter(
                    checkbox =>
                        checkbox.checked
                );

            const count =
                selected.length;

            if (selectedCount) {
                selectedCount.textContent =
                    count;
            }

            if (bulkBar) {
                bulkBar.style.display =
                    count > 0
                        ? 'flex'
                        : 'none';
            }

            if (selectAll) {

                const all =
                    checkboxes().length > 0
                    && count === checkboxes().length;

                selectAll.checked =
                    all;

                selectAll.indeterminate =
                    count > 0
                    && !all;
            }
        }


        if (selectAll) {

            selectAll.addEventListener(
                'change',
                function () {

                    checkboxes().forEach(
                        checkbox => {
                            checkbox.checked =
                                selectAll.checked;
                        }
                    );

                    updateSelectionUI();
                }
            );

        }


        checkboxes().forEach(
            checkbox => {

                checkbox.addEventListener(
                    'change',
                    updateSelectionUI
                );

            }
        );


        if (clearButton) {

            clearButton.addEventListener(
                'click',
                function () {

                    checkboxes().forEach(
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
        | Individual Push
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

                            const originalText =
                                button.innerHTML;

                            button.disabled =
                                true;

                            button.innerHTML =
                                '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

                            try {

                                const response =
                                    await fetch(
                                        '{{ route('products.push', ['productId' => '__PRODUCT_ID__']) }}'
                                            .replace(
                                                '__PRODUCT_ID__',
                                                encodeURIComponent(
                                                    productId
                                                )
                                            ),
                                        {
                                            method: 'POST',

                                            headers: {
                                                'Content-Type':
                                                    'application/json',

                                                'X-CSRF-TOKEN':
                                                    document
                                                        .querySelector(
                                                            'meta[name="csrf-token"]'
                                                        )
                                                        ?.getAttribute(
                                                            'content'
                                                        ),

                                                'Accept':
                                                    'application/json'
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
                                        || 'Unable to push product.'
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
                                    originalText;
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
                        checkboxes()
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

                    if (
                        !confirm(
                            `Push ${selected.length} selected product(s) to Shopify?`
                        )
                    ) {
                        return;
                    }

                    const originalText =
                        pushSelectedButton.innerHTML;

                    pushSelectedButton.disabled =
                        true;

                    pushSelectedButton.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

                    try {

                        const response =
                            await fetch(
                                '{{ route('products.push-selected') }}',
                                {
                                    method: 'POST',

                                    headers: {
                                        'Content-Type':
                                            'application/json',

                                        'X-CSRF-TOKEN':
                                            document
                                                .querySelector(
                                                    'meta[name="csrf-token"]'
                                                )
                                                ?.getAttribute(
                                                    'content'
                                                ),

                                        'Accept':
                                            'application/json'
                                    },

                                    body:
                                        JSON.stringify({
                                            product_ids:
                                                selected
                                        })
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
                            || 'Products processed.'
                        );

                        window.location.reload();

                    } catch (error) {

                        alert(
                            error.message
                        );

                        pushSelectedButton.disabled =
                            false;

                        pushSelectedButton.innerHTML =
                            originalText;
                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Initial UI
        |--------------------------------------------------------------------------
        */

        updateSelectionUI();

    }
);

</script>

@endpush

@endsection