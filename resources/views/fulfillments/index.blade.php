@extends('layouts.app')

@section('title', 'Fulfillments - CWFSAPI')

@section('page-title', 'Fulfillments')

@section('content')

<div class="container-fluid px-0">

    {{-- =========================================================
         Page Header
    ========================================================== --}}

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="fw-semibold mb-1">
                Fulfillments
            </h3>

            <p class="text-muted mb-0">
                View and manage Fullscript fulfillment orders.
            </p>

        </div>

        <div>

            <span class="badge bg-primary px-3 py-2">

                <i class="bi bi-box-seam me-1"></i>

                Fulfillments

            </span>

        </div>

    </div>


    {{-- =========================================================
         Fulfillment Card
    ========================================================== --}}

    <div class="card dashboard-card">

        <div class="card-body p-4">


            {{-- Card Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4">

                <div class="d-flex align-items-center">

                    <div class="status-icon success me-3">

                        <i class="bi bi-box-seam"></i>

                    </div>

                    <div>

                        <h5 class="mb-1">
                            Fulfillment Orders
                        </h5>

                        <small class="text-muted">
                            Shopify and Fullscript fulfillment information
                        </small>

                    </div>

                </div>


                @if($fulfillments->count())

                    <span class="badge bg-success px-3 py-2">

                        <i class="bi bi-check-circle me-1"></i>

                        {{ $fulfillments->total() }} Orders

                    </span>

                @endif

            </div>


            {{-- =================================================
                 Fulfillment Table
            ================================================== --}}

            @if($fulfillments->count())

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Shopify Order Name
                                </th>

                                <th>
                                    Shopify Order
                                </th>

                                <th>
                                    Fullscript Order
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Created
                                </th>

                                <th class="text-center">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        @foreach($fulfillments as $fulfillment)

                            <tr>

                                {{-- ID --}}
                                <td>

                                    <strong>
                                        #{{ $fulfillment->id }}
                                    </strong>

                                </td>


                                {{-- Shopify Order Name --}}
                                <td>

                                    @if(!empty($fulfillment->shopify_order_name))

                                        <strong>
                                            {{ $fulfillment->shopify_order_name }}
                                        </strong>

                                    @else

                                        <span class="text-muted">
                                            -
                                        </span>

                                    @endif

                                </td>


                                {{-- Shopify Order --}}
                                <td>

                                    @if(!empty($fulfillment->shopify_order_id))

                                        <code class="text-break">
                                            {{ $fulfillment->shopify_order_id }}
                                        </code>

                                    @else

                                        <span class="text-muted">
                                            -
                                        </span>

                                    @endif

                                </td>


                                {{-- Fullscript Order --}}
                                <td>

                                    @if(!empty($fulfillment->fullscript_order_id))

                                        <strong>
                                            {{ $fulfillment->fullscript_order_id }}
                                        </strong>

                                    @else

                                        <span class="text-muted">
                                            -
                                        </span>

                                    @endif

                                </td>


                                {{-- Status --}}
                                <td>

                                    @php
                                        $status = strtolower(
                                            $fulfillment->status ?? ''
                                        );
                                    @endphp


                                    @if($status === 'shipped')

                                        <span class="badge bg-success">

                                            <i class="bi bi-check-circle me-1"></i>

                                            Shipped

                                        </span>


                                    @elseif($status === 'processing')

                                        <span class="badge bg-warning text-dark">

                                            <i class="bi bi-hourglass-split me-1"></i>

                                            Processing

                                        </span>


                                    @elseif($status === 'cancelled')

                                        <span class="badge bg-danger">

                                            <i class="bi bi-x-circle me-1"></i>

                                            Cancelled

                                        </span>


                                    @elseif($status === 'failed')

                                        <span class="badge bg-danger">

                                            <i class="bi bi-exclamation-circle me-1"></i>

                                            Failed

                                        </span>


                                    @else

                                        <span class="badge bg-secondary">

                                            {{ $fulfillment->status ?? 'Unknown' }}

                                        </span>

                                    @endif

                                </td>


                                {{-- Created --}}
                                <td>

                                    @if($fulfillment->created_at)

                                        <div>
                                            {{ $fulfillment->created_at->format('Y-m-d') }}
                                        </div>

                                        <small class="text-muted">

                                            {{ $fulfillment->created_at->format('H:i:s') }}

                                        </small>

                                    @else

                                        <span class="text-muted">
                                            -
                                        </span>

                                    @endif

                                </td>


                                {{-- View --}}
                                <td class="text-center">

                                    <a
                                        href="{{ route('fulfillments.show', $fulfillment) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >

                                        <i class="bi bi-eye me-1"></i>

                                        View

                                    </a>

                                </td>

                            </tr>

                        @endforeach

                        </tbody>

                    </table>

                </div>


                {{-- =================================================
                     Pagination
                ================================================== --}}

                @if($fulfillments->hasPages())

                    <div
                        class="d-flex justify-content-between align-items-center mt-4"
                    >

                        <div class="text-muted small">

                            Showing

                            <strong>
                                {{ $fulfillments->firstItem() }}
                            </strong>

                            to

                            <strong>
                                {{ $fulfillments->lastItem() }}
                            </strong>

                            of

                            <strong>
                                {{ $fulfillments->total() }}
                            </strong>

                            fulfillment orders

                        </div>


                        <div>

                            {{ $fulfillments->links() }}

                        </div>

                    </div>

                @endif


            @else

                {{-- =================================================
                     Empty State
                ================================================== --}}

                <div class="text-center py-5">

                    <div class="status-icon success mx-auto mb-3">

                        <i class="bi bi-box-seam"></i>

                    </div>


                    <h5 class="mb-2">
                        No Fulfillment Orders
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