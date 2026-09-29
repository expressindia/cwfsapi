{{-- Fulfillments --}}
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">FSWarehouse Fulfillment</h5>
    </div>

    <div class="card-body">

        @forelse($order['fulfillments'] ?? [] as $fulfillment)

            @php
                $trackingInfo = $fulfillment['trackingInfo'] ?? [];

                $hasTracking = collect($trackingInfo)
                    ->contains(function ($tracking) {
                        return filled($tracking['number'] ?? null);
                    });
            @endphp

            <div class="border rounded p-3 mb-3">

                {{-- Fulfillment Header --}}
                <div class="d-flex justify-content-between align-items-center mb-3">

                    <div>
                        <strong>Fulfillment</strong>

                        <div class="small text-muted">
                            {{ $fulfillment['id'] ?? '-' }}
                        </div>
                    </div>

                    <span class="badge bg-secondary">
                        {{ $fulfillment['status'] ?? '-' }}
                    </span>

                </div>


                {{-- Tracking Information --}}
                <div class="mb-3">

                    <h6 class="mb-3">
                        Tracking Information
                    </h6>

                    @if($hasTracking)

                        @foreach($trackingInfo as $tracking)

                            @if(filled($tracking['number'] ?? null))

                                <div class="border rounded p-3 mb-2">

                                    <div class="row">

                                        <div class="col-md-4">
                                            <div class="text-muted small">
                                                Carrier
                                            </div>

                                            <strong>
                                                {{ $tracking['company'] ?? '-' }}
                                            </strong>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="text-muted small">
                                                Tracking Number
                                            </div>

                                            <strong>
                                                {{ $tracking['number'] }}
                                            </strong>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="text-muted small">
                                                Tracking
                                            </div>

                                            @if(filled($tracking['url'] ?? null))

                                                <a
                                                    href="{{ $tracking['url'] }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="btn btn-sm btn-outline-primary"
                                                >
                                                    Track Package
                                                </a>

                                            @else
                                                -
                                            @endif

                                        </div>

                                    </div>

                                </div>

                            @endif

                        @endforeach

                    @else

                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            No tracking information has been added yet.
                        </div>

                    @endif

                </div>


                {{-- Add / Update Tracking --}}
                <div>

                    @if($hasTracking)

                        <button
                            class="btn btn-sm btn-outline-primary"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#trackingForm{{ str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id']) }}"
                        >
                            <i class="bi bi-pencil"></i>
                            Update Tracking Information
                        </button>

                    @else

                        <button
                            class="btn btn-sm btn-primary"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#trackingForm{{ str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id']) }}"
                        >
                            <i class="bi bi-plus-circle"></i>
                            Add Tracking Information
                        </button>

                    @endif


                    {{-- Form --}}
                    <div
                        class="collapse mt-3"
                        id="trackingForm{{ str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id']) }}"
                    >

                        <div class="card card-body bg-light">

                            <form
                                method="POST"
                                action="{{ route('orders.tracking.update', [
                                    'orderId' => str_replace(
                                        'gid://shopify/Order/',
                                        '',
                                        $order['id']
                                    ),
                                    'fulfillmentId' => str_replace(
                                        'gid://shopify/Fulfillment/',
                                        '',
                                        $fulfillment['id']
                                    ),
                                ]) }}"
                            >

                                @csrf

                                <div class="row">

                                    {{-- Carrier --}}
                                    <div class="col-md-5 mb-3">

                                        <label
                                            for="company{{ str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id']) }}"
                                            class="form-label"
                                        >
                                            Shipping Carrier
                                        </label>

                                        <select
                                            name="company"
                                            id="company{{ str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id']) }}"
                                            class="form-select"
                                            required
                                        >

                                            <option value="">
                                                Select carrier
                                            </option>

                                            @foreach(config('shopify.tracking_carriers', []) as $carrier)

                                                <option
                                                    value="{{ $carrier }}"
                                                    @selected(
                                                        old('company') === $carrier
                                                        ||
                                                        (
                                                            $hasTracking
                                                            && isset($trackingInfo[0]['company'])
                                                            && $trackingInfo[0]['company'] === $carrier
                                                        )
                                                    )
                                                >
                                                    {{ $carrier }}
                                                </option>

                                            @endforeach

                                        </select>

                                    </div>


                                    {{-- Tracking Number --}}
                                    <div class="col-md-5 mb-3">

                                        <label
                                            for="tracking_number{{ str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id']) }}"
                                            class="form-label"
                                        >
                                            Tracking Number
                                        </label>

                                        <input
                                            type="text"
                                            name="tracking_number"
                                            id="tracking_number{{ str_replace('gid://shopify/Fulfillment/', '', $fulfillment['id']) }}"
                                            class="form-control"
                                            value="{{ old(
                                                'tracking_number',
                                                $hasTracking
                                                    ? ($trackingInfo[0]['number'] ?? '')
                                                    : ''
                                            ) }}"
                                            placeholder="Enter tracking number"
                                            required
                                        >

                                    </div>


                                    {{-- Submit --}}
                                    <div class="col-md-2 mb-3 d-flex align-items-end">

                                        <button
                                            type="submit"
                                            class="btn btn-success w-100"
                                        >
                                            @if($hasTracking)
                                                Update
                                            @else
                                                Add
                                            @endif
                                        </button>

                                    </div>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        @empty

            <div class="alert alert-info mb-0">
                No FSWarehouse fulfillment found for this order.
            </div>

        @endforelse

    </div>
</div>