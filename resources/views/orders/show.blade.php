@extends('layouts.app')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Order Details</h1>
            <p class="text-muted mb-0">
                Shopify order details
            </p>
        </div>

        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
            Back to Orders
        </a>
    </div>

    <div class="card">
        <div class="card-body">

            <h5 class="card-title">Shopify Order</h5>

            <p class="mb-0">
                <strong>Order ID:</strong>
                {{ $orderId }}
            </p>

        </div>
    </div>

</div>

@endsection