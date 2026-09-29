@extends('layouts.app')

@section('title', 'Fulfillments - CWFSAPI')

@section('page-title', 'Fulfillments')

@section('content')

<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="fw-semibold mb-1">
                Fulfillments
            </h3>

            <p class="text-muted mb-0">
                View and manage fulfillment orders.
            </p>

        </div>

        <div>

            <span class="badge bg-primary px-3 py-2">

                <i class="bi bi-box-seam me-1"></i>

                Fulfillments

            </span>

        </div>

    </div>


    {{-- Fulfillment Card --}}
    <div class="card dashboard-card">

        <div class="card-body p-4">

            @if($fulfillments->count())

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Fullscript Order</th>
                                <th>Shopify Order</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>

                        </thead>

                        <tbody>

                        @foreach($fulfillments as $fulfillment)

                            <tr>

                                <td>
                                    {{ $fulfillment->id }}
                                </td>

                                <td>
                                    {{ $fulfillment->fullscript_order_id ?? '-' }}
                                </td>

                                <td>
                                    {{ $fulfillment->shopify_order_id ?? '-' }}
                                </td>

                                <td>

                                    <span class="badge bg-secondary">

                                        {{ $fulfillment->status ?? 'Unknown' }}

                                    </span>

                                </td>

                                <td>

                                    {{ $fulfillment->created_at?->format('Y-m-d H:i:s') ?? '-' }}

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>


                <div class="mt-4">

                    {{ $fulfillments->links() }}

                </div>

            @else

                <div class="text-center py-5">

                    <div class="status-icon success mx-auto mb-3">

                        <i class="bi bi-box-seam"></i>

                    </div>

                    <h5 class="mb-2">
                        No Fulfillments
                    </h5>

                    <p class="text-muted mb-0">
                        No fulfillment orders have been created yet.
                    </p>

                </div>

            @endif

        </div>

    </div>

</div>

@endsection