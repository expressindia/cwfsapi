@extends('layouts.app')

@section('content')

<div class="container-fluid py-4">

    {{-- ============================================================
         PAGE HEADER
    ============================================================= --}}
    <div class="mb-4">

        <h1 class="h3 mb-1">
            Vendor Inventory
        </h1>

        <p class="text-muted mb-0">
            Activate FSWarehouse and deactivate Headquarters for a selected brand.
        </p>

    </div>


    {{-- ============================================================
         ERROR
    ============================================================= --}}
    @if(session('error'))

        <div class="alert alert-danger mb-4">
            <strong>Error:</strong>
            {{ session('error') }}
        </div>

    @endif


    {{-- ============================================================
         SUCCESS
    ============================================================= --}}
    @if(session('success'))

        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>

    @endif


    {{-- ============================================================
         VALIDATION ERRORS
    ============================================================= --}}
    @if($errors->any())

        <div class="alert alert-danger mb-4">

            <strong>Please correct the following:</strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif



    {{-- ============================================================
         STEP 1 — SELECT BRAND
    ============================================================= --}}
    <div class="card mb-4">

        <div class="card-header fw-semibold">
            Step 1 — Select Brand
        </div>


        <div class="card-body">

            <form
                method="POST"
                action="{{ route('vendor-inventory.preview') }}"
            >

                @csrf

                <div class="row g-3 align-items-center">

                    {{-- Brand / Vendor --}}
                    <div class="col-md-8">

                        <label
                            for="vendor"
                            class="form-label mb-1"
                        >
                            Brand / Vendor Name
                        </label>

                        <input
                            type="text"
                            name="vendor"
                            id="vendor"
                            class="form-control"
                            value="{{ old('vendor', $vendor ?? '') }}"
                            placeholder="Enter brand name"
                            required
                        >

                        <div class="form-text">
                            Example: <strong>A.C. Grace</strong>
                        </div>

                    </div>


                    {{-- Preview Button --}}
                    <div class="col-md-4 d-flex justify-content-md-end align-items-center">

                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                        >
                            Preview Inventory
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>



    {{-- ============================================================
         STEP 2 — INVENTORY PREVIEW
    ============================================================= --}}
    @if(!empty($preview))

        <div class="card mb-4">

            <div class="card-header fw-semibold">
                Step 2 — Inventory Preview
            </div>


            <div class="card-body">

                {{-- =================================================
                     BASIC INFORMATION
                ================================================== --}}
                <div class="row g-4">

                    {{-- Vendor --}}
                    <div class="col-md-3">

                        <div class="text-muted small mb-1">
                            Brand / Vendor
                        </div>

                        <div class="fw-semibold">
                            {{ $preview['vendor'] ?? ($vendor ?? '-') }}
                        </div>

                    </div>


                    {{-- Target Location --}}
                    <div class="col-md-3">

                        <div class="text-muted small mb-1">
                            Target Location
                        </div>

                        <div class="fw-semibold">

                            {{
                                $preview['target_location']['name']
                                ?? 'FSWarehouse'
                            }}

                        </div>

                    </div>


                    {{-- Products --}}
                    <div class="col-md-3">

                        <div class="text-muted small mb-1">
                            Products
                        </div>

                        <div class="fw-semibold">

                            {{
                                number_format(
                                    $preview['summary']['products']
                                    ?? $preview['product_count']
                                    ?? 0
                                )
                            }}

                        </div>

                    </div>


                    {{-- Variants --}}
                    <div class="col-md-3">

                        <div class="text-muted small mb-1">
                            Variants
                        </div>

                        <div class="fw-semibold">

                            {{
                                number_format(
                                    $preview['summary']['variants']
                                    ?? $preview['variant_count']
                                    ?? 0
                                )
                            }}

                        </div>

                    </div>

                </div>


                <hr class="my-4">


                {{-- =================================================
                     IMPORTANT NOTICE
                ================================================== --}}
                <div class="alert alert-info mb-4">

                    <strong>No inventory quantities will be changed.</strong>

                    <div class="mt-1">

                        This workflow only changes the inventory
                        location activation status.

                        Inventory quantities remain unchanged.

                    </div>

                </div>


                {{-- =================================================
                     INVENTORY SUMMARY
                ================================================== --}}
                <div class="row g-4">

                    {{-- Total Inventory --}}
                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Total Active Inventory
                        </div>

                        <div class="fs-5 fw-semibold">

                            {{
                                number_format(
                                    $preview['summary']['total_inventory']
                                    ?? $preview['total_inventory']
                                    ?? 0
                                )
                            }}

                        </div>

                    </div>


                    {{-- FSWarehouse Inventory --}}
                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Current FSWarehouse Inventory
                        </div>

                        <div class="fs-5 fw-semibold">

                            {{
                                number_format(
                                    $preview['summary']['fswarehouse_current_inventory']
                                    ?? $preview['fswarehouse_current_inventory']
                                    ?? 0
                                )
                            }}

                        </div>

                    </div>


                    {{-- Quantity Change --}}
                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Quantity Change
                        </div>

                        <div class="fs-5 fw-semibold text-success">
                            0
                        </div>

                    </div>

                </div>

            </div>

        </div>



        {{-- ============================================================
             INVENTORY DETAILS
        ============================================================= --}}
        <div class="card mb-4">

            <div class="card-header fw-semibold">
                Inventory Details
            </div>


            <div class="card-body p-0">

                @if(!empty($preview['variants']))

                    <div class="table-responsive">

                        <table class="table table-bordered table-hover mb-0 align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Variant
                                    </th>

                                    <th>
                                        SKU
                                    </th>

                                    <th>
                                        FSWarehouse
                                    </th>

                                    <th>
                                        Other Locations
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @foreach($preview['variants'] as $row)

                                    @php

                                        $targetLocationId =
                                            $preview['target_location']['id']
                                            ?? null;

                                        $fsLocation = collect(
                                            $row['locations'] ?? []
                                        )->first(
                                            function ($location) use ($targetLocationId) {

                                                return (
                                                    ($location['location_id'] ?? null)
                                                    ===
                                                    $targetLocationId
                                                );

                                            }
                                        );

                                        $otherLocations = collect(
                                            $row['locations'] ?? []
                                        )->filter(
                                            function ($location) use ($targetLocationId) {

                                                return (
                                                    ($location['location_id'] ?? null)
                                                    !==
                                                    $targetLocationId
                                                );

                                            }
                                        );

                                        $fsIsActive = (bool) (
                                            $fsLocation['is_active'] ?? false
                                        );

                                    @endphp


                                    <tr>

                                        {{-- Product --}}
                                        <td>
                                            {{ $row['product_title'] ?? '-' }}
                                        </td>


                                        {{-- Variant --}}
                                        <td>
                                            {{ $row['variant_title'] ?? '-' }}
                                        </td>


                                        {{-- SKU --}}
                                        <td>

                                            @if(!empty($row['sku']))

                                                <code>
                                                    {{ $row['sku'] }}
                                                </code>

                                            @else

                                                <span class="text-muted">
                                                    -
                                                </span>

                                            @endif

                                        </td>


                                        {{-- FSWarehouse --}}
                                        <td>

                                            @if($fsLocation)

                                                <div class="mb-1">

                                                    <strong>
                                                        {{
                                                            number_format(
                                                                $fsLocation['quantity'] ?? 0
                                                            )
                                                        }}
                                                    </strong>

                                                </div>


                                                @if($fsIsActive)

                                                    <span class="badge bg-success">
                                                        Active
                                                    </span>

                                                @else

                                                    <span class="badge bg-secondary">
                                                        Inactive
                                                    </span>

                                                @endif

                                            @else

                                                <span class="text-muted">
                                                    Not available
                                                </span>

                                            @endif

                                        </td>


                                        {{-- Other Locations --}}
                                        <td>

                                            @forelse($otherLocations as $location)

                                                <div class="mb-2">

                                                    <div class="d-flex justify-content-between gap-3">

                                                        <span>

                                                            {{
                                                                $location['location_name']
                                                                ?? 'Unknown'
                                                            }}

                                                        </span>


                                                        <strong>

                                                            {{
                                                                number_format(
                                                                    $location['quantity'] ?? 0
                                                                )
                                                            }}

                                                        </strong>

                                                    </div>


                                                    <div class="mt-1">

                                                        @if($location['is_active'] ?? false)

                                                            <span class="badge bg-success">
                                                                Active
                                                            </span>

                                                        @else

                                                            <span class="badge bg-secondary">
                                                                Inactive
                                                            </span>

                                                        @endif

                                                    </div>

                                                </div>

                                            @empty

                                                <span class="text-muted">
                                                    None
                                                </span>

                                            @endforelse

                                        </td>


                                        {{-- Status --}}
                                        <td>

                                            @if($fsIsActive)

                                                <span class="badge bg-success">
                                                    FSWarehouse Active
                                                </span>

                                            @else

                                                <span class="badge bg-warning text-dark">
                                                    FSWarehouse Inactive
                                                </span>

                                            @endif

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <div class="p-4 text-center text-muted">

                        No inventory variants were found for this vendor.

                    </div>

                @endif

            </div>

        </div>



        {{-- ============================================================
             STEP 3 — ACTIVATE FSWAREHOUSE
        ============================================================= --}}
        @if(!empty($preview['variants']))

            <div class="card border-primary mb-4">

                <div class="card-header fw-semibold">
                    Step 3 — Activate FS-Warehouse
                </div>


                <div class="card-body">

                    <p class="mb-3">

                        Activate

                        <strong>
                            {{
                                $preview['target_location']['name']
                                ?? 'FSWarehouse'
                            }}
                        </strong>

                        for all variants belonging to this brand.

                    </p>


                    <div class="alert alert-info">

                        <strong>Important:</strong>

                        No inventory quantities will be changed.

                        Only the inventory location activation
                        status will be updated.

                    </div>


                    <form
                        method="POST"
                        action="{{ route('vendor-inventory.activate') }}"
                        onsubmit="return confirm(
                            'Are you sure you want to activate FSWarehouse for this brand? Inventory quantities will not be changed.'
                        );"
                    >

                        @csrf

                        <input
                            type="hidden"
                            name="vendor"
                            value="{{ $preview['vendor'] ?? ($vendor ?? '') }}"
                        >


                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                        >
                            Activate FSWarehouse
                        </button>

                    </form>

                </div>

            </div>

        @endif

    @endif



    {{-- ============================================================
         ACTIVATION RESULT
    ============================================================= --}}
    @if(!empty($activationResult))

        @php

            $activationSummary =
                $activationResult['summary'] ?? [];

            $activationResults =
                $activationResult['results'] ?? [];

            $activationTotal =
                $activationSummary['total']
                ?? count($activationResults);

            $activationSuccessful =
                $activationSummary['successful']
                ?? $activationSummary['success']
                ?? collect($activationResults)
                    ->where('success', true)
                    ->count();

            $activationFailed =
                $activationSummary['failed']
                ?? collect($activationResults)
                    ->where('success', false)
                    ->count();

        @endphp


        <div class="card mb-4">

            <div class="card-header fw-semibold">
                Step 3 — Activation Result
            </div>


            <div class="card-body">

                {{-- Summary --}}
                <div class="row g-4 mb-4">

                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Total Variants
                        </div>

                        <div class="fs-5 fw-semibold">
                            {{ number_format($activationTotal) }}
                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Successful
                        </div>

                        <div class="fs-5 fw-semibold text-success">
                            {{ number_format($activationSuccessful) }}
                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Failed
                        </div>

                        <div class="fs-5 fw-semibold text-danger">
                            {{ number_format($activationFailed) }}
                        </div>

                    </div>

                </div>


                {{-- Individual Results --}}
                @if(!empty($activationResults))

                    <div class="table-responsive">

                        <table class="table table-bordered table-hover align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Variant
                                    </th>

                                    <th>
                                        SKU
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Message
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @foreach($activationResults as $result)

                                    <tr>

                                        <td>
                                            {{ $result['product_title'] ?? '-' }}
                                        </td>

                                        <td>
                                            {{ $result['variant_title'] ?? '-' }}
                                        </td>

                                        <td>

                                            <code>
                                                {{ $result['sku'] ?? '-' }}
                                            </code>

                                        </td>

                                        <td>

                                            @if($result['success'] ?? false)

                                                <span class="badge bg-success">
                                                    Success
                                                </span>

                                            @else

                                                <span class="badge bg-danger">
                                                    Failed
                                                </span>

                                            @endif

                                        </td>

                                        <td>
                                            {{ $result['message'] ?? '-' }}
                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @endif


                {{-- Success Message --}}
                @if(
                    $activationFailed === 0
                    && $activationTotal > 0
                )

                    <div class="alert alert-success mt-4 mb-0">

                        <strong>
                            FSWarehouse activation completed successfully.
                        </strong>

                        <div class="mt-1">

                            Please verify the activation before
                            deactivating Headquarters.

                        </div>

                    </div>

                @endif

            </div>

        </div>



        {{-- ============================================================
             STEP 4 — VERIFY ACTIVATION
        ============================================================= --}}
        @if($activationTotal > 0)

            <div class="card border-info mb-4">

                <div class="card-header fw-semibold">
                    Step 4 — Verify Activation
                </div>


                <div class="card-body">

                    <p class="mb-3">

                        Run a fresh Shopify check to confirm that
                        FSWarehouse is active for every variant.

                    </p>


                    <form
                        method="POST"
                        action="{{ route('vendor-inventory.verify-activation') }}"
                    >

                        @csrf

                        <input
                            type="hidden"
                            name="vendor"
                            value="{{ $activationResult['vendor'] ?? ($vendor ?? '') }}"
                        >


                        <button
                            type="submit"
                            class="btn btn-info text-white px-4"
                        >
                            Verify Activation
                        </button>

                    </form>

                </div>

            </div>

        @endif

    @endif



    {{-- ============================================================
         ACTIVATION VERIFICATION RESULT
    ============================================================= --}}
    @if(!empty($activationVerification))

        @php

            $verificationPassed =
                (bool) (
                    $activationVerification['verified']
                    ?? $activationVerification['success']
                    ?? false
                );

        @endphp


        <div class="card mb-4">

            <div class="card-header fw-semibold">
                Step 4 — Verification Result
            </div>


            <div class="card-body">

                @if($verificationPassed)

                    {{-- Verification successful --}}
                    <div class="alert alert-success">

                        <strong>
                            FSWarehouse activation verified.
                        </strong>

                        <div class="mt-1">

                            All variants are active at FSWarehouse.

                            You can now proceed to deactivate
                            Headquarters.

                        </div>

                    </div>


                    {{-- =================================================
                         STEP 5 — DEACTIVATE HEADQUARTERS
                    ================================================== --}}
                    <div class="card border-warning mt-4">

                        <div class="card-header fw-semibold">
                            Step 5 — Deactivate Headquarters
                        </div>


                        <div class="card-body">

                            <p class="mb-3">

                                Deactivate the
                                <strong>Headquarters</strong>
                                inventory location for this brand.

                            </p>


                            <div class="alert alert-warning">

                                <strong>Important:</strong>

                                Only the Headquarters location
                                activation status will be changed.

                                Inventory quantities will remain
                                unchanged.

                            </div>


                            <form
                                method="POST"
                                action="{{ route('vendor-inventory.deactivate') }}"
                                onsubmit="return confirm(
                                    'Are you sure you want to deactivate Headquarters for this brand? Inventory quantities will not be changed.'
                                );"
                            >

                                @csrf

                                <input
                                    type="hidden"
                                    name="vendor"
                                    value="{{
                                        $activationVerification['vendor']
                                        ?? ($vendor ?? '')
                                    }}"
                                >


                                <div class="form-check mb-3">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="confirm"
                                        value="1"
                                        id="confirmDeactivate"
                                        required
                                    >

                                    <label
                                        class="form-check-label"
                                        for="confirmDeactivate"
                                    >
                                        I confirm that FSWarehouse has been
                                        verified and I want to deactivate
                                        Headquarters.
                                    </label>

                                </div>


                                <button
                                    type="submit"
                                    class="btn btn-warning px-4"
                                >
                                    Deactivate Headquarters
                                </button>

                            </form>

                        </div>

                    </div>

                @else

                    {{-- Verification failed --}}
                    <div class="alert alert-danger mb-0">

                        <strong>
                            FSWarehouse activation could not be verified.
                        </strong>

                        <div class="mt-1">

                            Headquarters should not be deactivated
                            until FSWarehouse activation is verified.

                        </div>

                    </div>

                @endif

            </div>

        </div>

    @endif



    {{-- ============================================================
         DEACTIVATION RESULT
    ============================================================= --}}
    @if(!empty($deactivationResult))

        @php

            $deactivationSummary =
                $deactivationResult['summary'] ?? [];

            $deactivationResults =
                $deactivationResult['results'] ?? [];

            $deactivationTotal =
                $deactivationSummary['total']
                ?? count($deactivationResults);

            $deactivationSuccessful =
                $deactivationSummary['successful']
                ?? $deactivationSummary['success']
                ?? collect($deactivationResults)
                    ->where('success', true)
                    ->count();

            $deactivationFailed =
                $deactivationSummary['failed']
                ?? collect($deactivationResults)
                    ->where('success', false)
                    ->count();

        @endphp


        <div class="card mb-4">

            <div class="card-header fw-semibold">
                Step 5 — Deactivation Result
            </div>


            <div class="card-body">

                {{-- Summary --}}
                <div class="row g-4 mb-4">

                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Total Variants
                        </div>

                        <div class="fs-5 fw-semibold">
                            {{ number_format($deactivationTotal) }}
                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Successful
                        </div>

                        <div class="fs-5 fw-semibold text-success">
                            {{ number_format($deactivationSuccessful) }}
                        </div>

                    </div>


                    <div class="col-md-4">

                        <div class="text-muted small mb-1">
                            Failed
                        </div>

                        <div class="fs-5 fw-semibold text-danger">
                            {{ number_format($deactivationFailed) }}
                        </div>

                    </div>

                </div>


                {{-- Individual Results --}}
                @if(!empty($deactivationResults))

                    <div class="table-responsive">

                        <table class="table table-bordered table-hover align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Product
                                    </th>

                                    <th>
                                        Variant
                                    </th>

                                    <th>
                                        SKU
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Message
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @foreach($deactivationResults as $result)

                                    <tr>

                                        <td>
                                            {{ $result['product_title'] ?? '-' }}
                                        </td>

                                        <td>
                                            {{ $result['variant_title'] ?? '-' }}
                                        </td>

                                        <td>

                                            <code>
                                                {{ $result['sku'] ?? '-' }}
                                            </code>

                                        </td>

                                        <td>

                                            @if($result['success'] ?? false)

                                                <span class="badge bg-success">
                                                    Success
                                                </span>

                                            @else

                                                <span class="badge bg-danger">
                                                    Failed
                                                </span>

                                            @endif

                                        </td>

                                        <td>
                                            {{ $result['message'] ?? '-' }}
                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @endif


                {{-- Completed --}}
                @if(
                    $deactivationFailed === 0
                    && $deactivationTotal > 0
                )

                    <div class="alert alert-success mt-4 mb-0">

                        <strong>
                            Workflow completed successfully.
                        </strong>

                        <div class="mt-1">

                            FSWarehouse is active and Headquarters
                            has been deactivated.

                            <br>

                            Inventory quantities were not changed.

                        </div>

                    </div>

                @endif

            </div>

        </div>

    @endif



    {{-- ============================================================
         FINAL COMPLETED MESSAGE
    ============================================================= --}}
    @if(!empty($deactivationResult))

        @php

            $finalSummary =
                $deactivationResult['summary'] ?? [];

            $finalFailed =
                $finalSummary['failed']
                ?? collect(
                    $deactivationResult['results'] ?? []
                )
                ->where('success', false)
                ->count();

        @endphp


        @if($finalFailed === 0)

            <div class="alert alert-success">

                <strong>
                    Completed
                </strong>

                <div class="mt-1">

                    The selected brand's inventory location workflow
                    has been completed.

                    FSWarehouse is active and Headquarters has been
                    deactivated.

                    Inventory quantities remain unchanged.

                </div>

            </div>

        @endif

    @endif

</div>

@endsection