@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Products</h1>
            <p class="text-muted mb-0">Browse Fullscript products and push them to Shopify.</p>
        </div>
        <button type="button" class="btn btn-primary" id="syncLatestProductsBtn">
            <i class="bi bi-arrow-repeat me-1"></i> Sync Latest Products
        </button>
    </div>

    @if($error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('products.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label">Brand / Vendor</label>
                        <input type="text" class="form-control" name="brand"
                               value="{{ $brand }}" placeholder="Example: Designs for Health">
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label">Product Name / SKU</label>
                        <input type="text" class="form-control" name="search"
                               value="{{ $search }}" placeholder="Enter product name or SKU">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Show per page</label>
                        <select class="form-select" name="per_page">
                            @foreach([25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-1">
                        <button class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                    </div>
                </div>

                <div class="mt-3">
                    <span class="text-muted me-2">Quick Filters:</span>
                    <a href="{{ route('products.index', ['per_page' => $perPage]) }}"
                       class="btn btn-sm {{ $brand === '' ? 'btn-primary' : 'btn-outline-primary' }} me-1 mb-1">
                        All Brands
                    </a>
                    @foreach(['Designs for Health','Allergy Research Group','A.C. Grace','Nordic Naturals','Thorne','Metagenics'] as $quickBrand)
                        <a href="{{ route('products.index', ['brand'=>$quickBrand,'per_page'=>$perPage]) }}"
                           class="btn btn-sm {{ strcasecmp($brand,$quickBrand)===0 ? 'btn-primary' : 'btn-outline-primary' }} me-1 mb-1">
                            {{ $quickBrand }}
                        </a>
                    @endforeach
                </div>
            </form>
        </div>
    </div>

    <div id="selectionBar" class="alert alert-primary d-none justify-content-between align-items-center">
        <span><span id="selectedCount">0</span> product(s) selected</span>
        <div>
            <button type="button" class="btn btn-primary btn-sm" id="pushSelectedBtn">
                <i class="bi bi-cloud-upload me-1"></i> Push Selected to Shopify
            </button>
            <button type="button" class="btn btn-light btn-sm" id="clearSelectionBtn">Clear Selection</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <strong>Fullscript Products</strong>
            <span class="text-muted">{{ number_format($totalProducts) }} products</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>SKU</th>
                    <th>Availability</th>
                    <th>Shopify Status</th>
                    <th>Updated</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
                    @php
                        $status = $product['shopify_status'] ?? 'Not Checked';
                        $statusClass = match($status) {
                            'Exists' => 'bg-info-subtle text-info-emphasis',
                            'Not Found' => 'bg-warning-subtle text-warning-emphasis',
                            'Error' => 'bg-danger-subtle text-danger-emphasis',
                            default => 'bg-secondary-subtle text-secondary-emphasis',
                        };
                        $availabilityClass = match(strtolower($product['availability'] ?? '')) {
                            'in stock' => 'bg-success-subtle text-success-emphasis',
                            'backordered' => 'bg-warning-subtle text-warning-emphasis',
                            'out of stock', 'discontinued' => 'bg-danger-subtle text-danger-emphasis',
                            default => 'bg-secondary-subtle text-secondary-emphasis',
                        };
                    @endphp
                    <tr>
                        <td>
                            @if(!empty($product['id']))
                                <input type="checkbox" class="form-check-input product-checkbox" value="{{ $product['id'] }}">
                            @endif
                        </td>
                        <td>
                            @if(!empty($product['image']))
                                <img src="{{ $product['image'] }}" alt="" class="rounded border"
                                     style="width:52px;height:52px;object-fit:contain;">
                            @else
                                <div class="rounded border bg-light d-flex align-items-center justify-content-center"
                                     style="width:52px;height:52px;"><i class="bi bi-box text-muted"></i></div>
                            @endif
                        </td>
                        <td>
                            @if(!empty($product['id']))
                                <a href="{{ route('products.show',['productId'=>$product['id']]) }}"
                                   class="fw-semibold text-decoration-none">{{ $product['title'] }}</a>
                            @else
                                <span class="fw-semibold">{{ $product['title'] }}</span>
                            @endif
                        </td>
                        <td>{{ $product['brand'] }}</td>
                        <td><code>{{ $product['sku'] ?: '—' }}</code></td>
                        <td><span class="badge {{ $availabilityClass }}">{{ $product['availability'] }}</span></td>
                        <td>
                            <span class="badge {{ $statusClass }}">{{ $status }}</span>
                            @if(!empty($product['shopify_status_text']))
                                <div class="small text-muted">{{ $product['shopify_status_text'] }}</div>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $product['updated_at'] ?: '—' }}</td>
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-sm {{ ($product['action'] ?? '') === 'update' ? 'btn-outline-primary' : 'btn-primary' }} push-product-btn"
                                    data-product-id="{{ $product['id'] ?? '' }}"
                                    data-shopify-product-id="{{ $product['shopify_product_id'] ?? '' }}">
                                <i class="bi bi-cloud-upload me-1"></i>
                                {{ ($product['action'] ?? '') === 'update' ? 'Update in Shopify' : 'Push to Shopify' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center py-5 text-muted">No products found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($paginator->hasPages())
            <div class="card-footer">{{ $paginator->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const boxes = () => [...document.querySelectorAll('.product-checkbox')];
    const selected = () => boxes().filter(x => x.checked).map(x => x.value);
    const bar = document.getElementById('selectionBar');
    const count = document.getElementById('selectedCount');

    function refreshSelection() {
        const ids = selected();
        count.textContent = ids.length;
        bar.classList.toggle('d-none', ids.length === 0);
        bar.classList.toggle('d-flex', ids.length > 0);
    }

    document.querySelectorAll('.product-checkbox').forEach(x => x.addEventListener('change', refreshSelection));

    document.getElementById('selectAll')?.addEventListener('change', e => {
        boxes().forEach(x => x.checked = e.target.checked);
        refreshSelection();
    });

    document.getElementById('clearSelectionBtn')?.addEventListener('click', () => {
        boxes().forEach(x => x.checked = false);
        document.getElementById('selectAll').checked = false;
        refreshSelection();
    });

    async function push(ids) {
        if (!ids.length) return alert('Please select at least one product.');

        try {
            const response = await fetch('{{ route('products.pushSelected') }}', {
                method: 'POST',
                headers: {'Accept':'application/json','Content-Type':'application/json',
                          'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                body: JSON.stringify({product_ids: ids})
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Product sync failed.');
            alert(data.message);
            location.reload();
        } catch (e) {
            alert(e.message);
        }
    }

    document.getElementById('pushSelectedBtn')?.addEventListener('click', () => push(selected()));

    document.querySelectorAll('.push-product-btn').forEach(button => {
        button.addEventListener('click', async () => {
            const id = button.dataset.productId;
            if (!id) return;
            button.disabled = true;

            try {
                const response = await fetch('{{ url('/products') }}/' + encodeURIComponent(id) + '/push', {
                    method:'POST',
                    headers:{'Accept':'application/json','Content-Type':'application/json',
                             'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                    body:JSON.stringify({shopify_product_id:button.dataset.shopifyProductId || null})
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Product sync failed.');
                alert(data.message);
                location.reload();
            } catch (e) {
                alert(e.message);
                button.disabled = false;
            }
        });
    });

    refreshSelection();
});
</script>
@endpush
