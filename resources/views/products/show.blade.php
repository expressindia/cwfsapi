@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <a href="{{ route('products.index') }}" class="text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i> Back to Products
    </a>

    @if($error)
        <div class="alert alert-danger mt-3">{{ $error }}</div>
    @endif

    @if($product)
        <div class="d-flex justify-content-between align-items-start my-4">
            <div>
                <h1 class="h3 mb-1">{{ $product['name'] ?? $product['title'] ?? 'Product' }}</h1>
                <p class="text-muted mb-0">{{ $brand }} · <code>{{ $primarySku ?: 'No SKU' }}</code></p>
            </div>
            <button id="pushProductBtn" type="button" class="btn btn-primary"
                    data-product-id="{{ $product['id'] }}"
                    data-shopify-product-id="{{ $shopifyInfo['product_id'] ?? '' }}">
                <i class="bi bi-cloud-upload me-1"></i>
                {{ ($shopifyInfo['action'] ?? '') === 'update' ? 'Update in Shopify' : 'Push to Shopify' }}
            </button>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Product Information</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Fullscript ID</dt>
                            <dd class="col-sm-9"><code>{{ $product['id'] ?? '—' }}</code></dd>
                            <dt class="col-sm-3">Brand</dt>
                            <dd class="col-sm-9">{{ $brand }}</dd>
                            <dt class="col-sm-3">Status</dt>
                            <dd class="col-sm-9">{{ $product['status'] ?? '—' }}</dd>
                            <dt class="col-sm-3">Description</dt>
                            <dd class="col-sm-9">{!! $product['description_html'] ?? $product['description'] ?? '—' !!}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header fw-semibold">Variants</div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr><th>SKU</th><th>UPC</th><th>Units</th><th>Unit</th><th>Availability</th><th>MSRP</th></tr>
                            </thead>
                            <tbody>
                            @forelse($variants as $variant)
                                <tr>
                                    <td><code>{{ $variant['sku'] ?? '—' }}</code></td>
                                    <td>{{ $variant['upc'] ?? '—' }}</td>
                                    <td>{{ $variant['units'] ?? '—' }}</td>
                                    <td>{{ $variant['unit_of_measure'] ?? '—' }}</td>
                                    <td>{{ $variant['availability'] ?? '—' }}</td>
                                    <td>{{ isset($variant['msrp']) ? '$'.number_format((float)$variant['msrp'],2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-4 text-muted">No variants found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Shopify Status</div>
                    <div class="card-body">
                        <div class="text-muted small">Status</div>
                        <div class="h5">{{ $shopifyInfo['status'] ?? 'Not Checked' }}</div>
                        <div class="text-muted small">Action</div>
                        <div>{{ $shopifyInfo['text'] ?? '—' }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header fw-semibold">Fullscript Data</div>
                    <div class="card-body">
                        <pre class="small mb-0" style="max-height:500px;overflow:auto;">{{ json_encode($product, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('pushProductBtn');
    if (!button) return;

    button.addEventListener('click', async () => {
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        try {
            const response = await fetch('{{ url('/products') }}/' + encodeURIComponent(button.dataset.productId) + '/push', {
                method:'POST',
                headers:{'Accept':'application/json','Content-Type':'application/json',
                         'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content,
                         'X-Requested-With':'XMLHttpRequest'},
                body:JSON.stringify({shopify_product_id:button.dataset.shopifyProductId || null})
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Product sync failed.');
            alert(data.message);
            location.reload();
        } catch (e) {
            alert(e.message);
            button.disabled = false;
            button.innerHTML = original;
        }
    });
});
</script>
@endpush
