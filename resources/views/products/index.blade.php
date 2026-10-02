@extends('layouts.app')

@section('title', 'Products - CWFSAPI')

@section('page-title', 'Products')

@section('content')

<style>
    .products-page {
        width: 100%;
    }

    .products-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 24px;
    }

    .products-title {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
    }

    .products-subtitle {
        margin: 6px 0 0;
        color: #6c757d;
    }

    .products-header-actions {
        text-align: right;
    }

    .last-synced {
        margin-top: 6px;
        font-size: 12px;
        color: #6c757d;
    }

    .products-filter-card {
        margin-bottom: 16px;
    }

    .brand-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 15px;
    }

    .brand-chip {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        background: #fff;
        color: #495057;
        text-decoration: none;
        font-size: 13px;
    }

    .brand-chip:hover,
    .brand-chip.active {
        background: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    .bulk-action-bar {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 12px 16px;
        margin-bottom: 16px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .bulk-selection {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .bulk-actions {
        display: flex;
        gap: 8px;
    }

    .products-table-card {
        overflow: hidden;
    }

    .products-table {
        min-width: 1050px;
    }

    .products-table th {
        white-space: nowrap;
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .03em;
        background: #f8f9fa;
    }

    .products-table td {
        vertical-align: middle;
    }

    .checkbox-column {
        width: 45px;
        text-align: center;
    }

    .image-column {
        width: 70px;
    }

    .product-image {
        width: 48px;
        height: 48px;
        object-fit: contain;
        border: 1px solid #eee;
        border-radius: 6px;
        background: #fff;
    }

    .product-image-placeholder {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #eee;
        border-radius: 6px;
        background: #f8f9fa;
        color: #adb5bd;
    }

    .product-name {
        font-weight: 600;
        color: #212529;
    }

    .product-sku {
        font-size: 12px;
        color: #6c757d;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-exists {
        background: #d1e7dd;
        color: #0f5132;
    }

    .status-not-found {
        background: #fff3cd;
        color: #664d03;
    }

    .status-error {
        background: #f8d7da;
        color: #842029;
    }

    .status-available {
        color: #198754;
    }

    .status-unavailable {
        color: #dc3545;
    }

    .pagination-wrapper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 16px;
    }

    .pagination-wrapper .pagination {
        margin: 0;
        flex-wrap: wrap;
    }

    .pagination-wrapper .page-link {
        min-width: 38px;
        text-align: center;
    }

    .products-empty {
        padding: 60px 20px;
        text-align: center;
        color: #6c757d;
    }

    @media (max-width: 768px) {
        .products-header {
            flex-direction: column;
        }

        .products-header-actions {
            text-align: left;
        }

        .bulk-action-bar {
            flex-direction: column;
            align-items: flex-start;
        }

        .pagination-wrapper {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="products-page">

    {{-- HEADER --}}
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
                class="btn btn-primary"
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

    {{-- ERROR --}}
    @if(!empty($error))

        <div class="alert alert-danger">

            <strong>
                Unable to load Fullscript products.
            </strong>

            <div class="mt-1">
                {{ $error }}
            </div>

        </div>

    @endif

    {{-- FILTER --}}
    <div class="card products-filter-card">

        <div class="card-body">

            <form
                method="GET"
                action="{{ route('products.index') }}"
                id="productSearchForm">

                <div class="row g-3 align-items-end">

                    {{-- BRAND --}}
                    <div class="col-md-4">

                        <label
                            for="brand_id"
                            class="form-label">

                            Brand / Vendor

                        </label>

                        <select
                            name="brand_id"
                            id="brand_id"
                            class="form-select">

                            <option value="">
                                All Brands
                            </option>

                            @foreach($brands ?? [] as $brand)

                                <option
                                    value="{{ $brand['id'] }}"
                                    {{ ($brandId ?? '') === $brand['id'] ? 'selected' : '' }}>

                                    {{ $brand['name'] }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                    {{-- SEARCH --}}
                    <div class="col-md-5">

                        <label
                            for="search"
                            class="form-label">

                            Product Name / SKU

                        </label>

                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ $search ?? '' }}"
                            class="form-control"
                            placeholder="Search product name or SKU">

                    </div>

                    {{-- PER PAGE --}}
                    <div class="col-md-2">

                        <label
                            for="per_page"
                            class="form-label">

                            Show

                        </label>

                        <select
                            name="per_page"
                            id="per_page"
                            class="form-select">

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

                    {{-- BUTTON --}}
                    <div class="col-md-1">

                        <button
                            type="submit"
                            class="btn btn-primary w-100">

                            <i class="bi bi-search"></i>

                        </button>

                    </div>

                </div>

            </form>

            {{-- QUICK BRAND FILTERS --}}
            <div class="brand-chips">

                <a
                    href="{{ route('products.index', [
                        'per_page' => $perPage ?? 25
                    ]) }}"
                    class="brand-chip {{ empty($brandId) ? 'active' : '' }}">

                    All Brands

                </a>

                @foreach($brands ?? [] as $brand)

                    <a
                        href="{{ route('products.index', [
                            'brand_id' => $brand['id'],
                            'per_page' => $perPage ?? 25
                        ]) }}"
                        class="brand-chip {{ ($brandId ?? '') === $brand['id'] ? 'active' : '' }}">

                        {{ $brand['name'] }}

                    </a>

                @endforeach

            </div>

        </div>

    </div>

    {{-- BULK ACTION BAR --}}
    <div
        class="bulk-action-bar"
        id="bulkActionBar">

        <div class="bulk-selection">

            <strong id="selectedCount">
                0
            </strong>

            products selected

            <span class="text-muted">
                (of {{ $paginator->count() }} on this page)
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

    {{-- PRODUCT TABLE --}}
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

                    @forelse($products as $product)

                        <tr>

                            {{-- CHECKBOX --}}
                            <td class="checkbox-column">

                                <input
                                    type="checkbox"
                                    class="form-check-input product-checkbox"
                                    value="{{ $product['id'] }}"
                                    data-sku="{{ $product['sku'] }}">

                            </td>

                            {{-- IMAGE --}}
                            <td>

                                @if(!empty($product['image']))

                                    <img
                                        src="{{ $product['image'] }}"
                                        alt="{{ $product['title'] }}"
                                        class="product-image">

                                @else

                                    <div class="product-image-placeholder">

                                        <i class="bi bi-image"></i>

                                    </div>

                                @endif

                            </td>

                            {{-- PRODUCT --}}
                            <td>

                                <div class="product-name">
                                    {{ $product['title'] }}
                                </div>

                                @if(!empty($product['variant_count']) && $product['variant_count'] > 1)

                                    <div class="product-sku">
                                        {{ $product['variant_count'] }} variants
                                    </div>

                                @endif

                            </td>

                            {{-- BRAND --}}
                            <td>
                                {{ $product['brand'] ?: '—' }}
                            </td>

                            {{-- SKU --}}
                            <td>

                                <span class="product-sku">
                                    {{ $product['sku'] ?: '—' }}
                                </span>

                            </td>

                            {{-- AVAILABILITY --}}
                            <td>

                                @if(
                                    strtolower(
                                        $product['availability'] ?? ''
                                    ) === 'in stock'
                                )

                                    <span class="status-available">
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        {{ $product['availability'] }}
                                    </span>

                                @else

                                    <span class="status-unavailable">
                                        {{ $product['availability'] }}
                                    </span>

                                @endif

                            </td>

                            {{-- SHOPIFY STATUS --}}
                            <td>

                                @if(
                                    ($product['shopify_status'] ?? '')
                                    === 'Exists'
                                )

                                    <span class="status-badge status-exists">

                                        <i class="bi bi-check-circle me-1"></i>

                                        Exists / Will update

                                    </span>

                                @elseif(
                                    ($product['shopify_status'] ?? '')
                                    === 'Not Found'
                                )

                                    <span class="status-badge status-not-found">

                                        <i class="bi bi-plus-circle me-1"></i>

                                        Not Found / Will create

                                    </span>

                                @else

                                    <span class="status-badge status-error">

                                        <i class="bi bi-exclamation-circle me-1"></i>

                                        {{ $product['shopify_status_text'] ?? 'Unable to check' }}

                                    </span>

                                @endif

                            </td>

                            {{-- UPDATED --}}
                            <td>

                                @if(!empty($product['updated_at']))

                                    {{ \Illuminate\Support\Carbon::parse($product['updated_at'])->format('M d, Y') }}

                                @else

                                    —

                                @endif

                            </td>

                            {{-- ACTION --}}
                            <td>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary product-action-button"
                                    data-id="{{ $product['id'] }}"
                                    data-title="{{ $product['title'] }}">

                                    @if(
                                        ($product['action'] ?? '')
                                        === 'update'
                                    )

                                        <i class="bi bi-arrow-repeat me-1"></i>
                                        Update

                                    @else

                                        <i class="bi bi-cloud-arrow-up me-1"></i>
                                        Push

                                    @endif

                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="9"
                                class="products-empty">

                                <i class="bi bi-box-seam fs-2"></i>

                                <div class="mt-2">
                                    No products found.
                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        {{-- PAGINATION --}}
        @if($paginator->total() > 0)

            <div class="pagination-wrapper">

                <div class="text-muted small">

                    Showing

                    {{ $paginator->firstItem() }}

                    to

                    {{ $paginator->lastItem() }}

                    of

                    {{ number_format($paginator->total()) }}

                    products

                </div>

                <div>

                    {{ $paginator->onEachSide(1)->links('pagination::bootstrap-5') }}

                </div>

            </div>

        @endif

    </div>

</div>

@endsection


@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const selectAll =
        document.getElementById('selectAll');

    const checkboxes =
        Array.from(
            document.querySelectorAll(
                '.product-checkbox'
            )
        );

    const bulkActionBar =
        document.getElementById(
            'bulkActionBar'
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

    const pushSelectedButton =
        document.getElementById(
            'pushSelectedButton'
        );

    const csrfToken =
        document.querySelector(
            'meta[name="csrf-token"]'
        )?.getAttribute('content');

    function updateSelection() {

        const selected =
            checkboxes.filter(
                checkbox =>
                    checkbox.checked
            );

        const count =
            selected.length;

        selectedCount.textContent =
            count;

        selectedButtonCount.textContent =
            count;

        bulkActionBar.style.display =
            count > 0
                ? 'flex'
                : 'none';

        if (selectAll) {

            selectAll.checked =
                count === checkboxes.length
                && checkboxes.length > 0;

            selectAll.indeterminate =
                count > 0
                && count < checkboxes.length;
        }
    }

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

                updateSelection();
            }
        );
    }

    checkboxes.forEach(
        checkbox => {

            checkbox.addEventListener(
                'change',
                updateSelection
            );

        }
    );

    if (clearSelectionButton) {

        clearSelectionButton.addEventListener(
            'click',
            function () {

                checkboxes.forEach(
                    checkbox => {
                        checkbox.checked = false;
                    }
                );

                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }

                updateSelection();
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
            '.product-action-button'
        )
        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    async function () {

                        const productId =
                            button.dataset.id;

                        const title =
                            button.dataset.title;

                        if (!productId) {
                            return;
                        }

                        if (
                            !confirm(
                                'Push "' +
                                title +
                                '" to Shopify?'
                            )
                        ) {
                            return;
                        }

                        const originalHtml =
                            button.innerHTML;

                        button.disabled = true;

                        button.innerHTML =
                            '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

                        try {

                            const response =
                                await fetch(
                                    '{{ url('/products') }}/' +
                                    encodeURIComponent(
                                        productId
                                    ) +
                                    '/push',
                                    {
                                        method: 'POST',

                                        headers: {
                                            'Content-Type':
                                                'application/json',

                                            'Accept':
                                                'application/json',

                                            'X-CSRF-TOKEN':
                                                csrfToken
                                        }
                                    }
                                );

                            const data =
                                await response.json();

                            if (!response.ok || !data.success) {
                                throw new Error(
                                    data.message ||
                                    'Product sync failed.'
                                );
                            }

                            alert(
                                data.message ||
                                'Product synced successfully.'
                            );

                            window.location.reload();

                        } catch (error) {

                            alert(
                                error.message
                                || 'Product sync failed.'
                            );

                            button.disabled = false;

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

                if (!selected.length) {
                    return;
                }

                if (
                    !confirm(
                        'Push ' +
                        selected.length +
                        ' selected product(s) to Shopify?'
                    )
                ) {
                    return;
                }

                const originalHtml =
                    pushSelectedButton.innerHTML;

                pushSelectedButton.disabled =
                    true;

                pushSelectedButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';

                try {

                    const response =
                        await fetch(
                            '{{ route('products.push-selected') }}',
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

                    let message =
                        data.message ||
                        'Bulk sync completed.';

                    if (Array.isArray(data.results)) {

                        const failed =
                            data.results.filter(
                                item =>
                                    !item.success
                            );

                        if (failed.length) {

                            message +=
                                '\n\nFailed products:\n' +
                                failed
                                    .map(
                                        item =>
                                            (
                                                item.sku
                                                || item.id
                                                || 'Unknown'
                                            )
                                            + ': '
                                            + item.message
                                    )
                                    .join('\n');
                        }
                    }

                    alert(message);

                    window.location.reload();

                } catch (error) {

                    alert(
                        error.message
                        || 'Bulk sync failed.'
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
    | Sync Latest Products
    |--------------------------------------------------------------------------
    |
    | Currently reloads the Fullscript catalog.
    | We are not inventing a Fullscript "latest" sort parameter because
    | that parameter has not been confirmed.
    |
    */

    const syncButton =
        document.getElementById(
            'syncLatestProducts'
        );

    if (syncButton) {

        syncButton.addEventListener(
            'click',
            function () {

                syncButton.disabled = true;

                syncButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span> Loading...';

                window.location.href =
                    '{{ route('products.index') }}';

            }
        );
    }

});
</script>

@endpush