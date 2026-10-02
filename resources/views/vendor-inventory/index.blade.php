@extends('layouts.app')

@section('content')

<div class="container-fluid py-4">

    {{-- =========================================================
         PAGE HEADER
    ========================================================== --}}
    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h1 class="h3 mb-1">
                Vendor Inventory
            </h1>

            <p class="text-muted mb-0">
                Activate FSWarehouse and deactivate Headquarters
                for a selected brand.
            </p>

        </div>

    </div>


    {{-- =========================================================
         SUCCESS MESSAGE
    ========================================================== --}}
    @if(session('success'))

        <div class="alert alert-success">

            {{ session('success') }}

        </div>

    @endif


    {{-- =========================================================
         ERROR MESSAGE
    ========================================================== --}}
    @if(session('error'))

        <div class="alert alert-danger">

            <strong>Error:</strong>

            {{ session('error') }}

        </div>

    @endif


    {{-- =========================================================
         VALIDATION ERRORS
    ========================================================== --}}
    @if($errors->any())

        <div class="alert alert-danger">

            <strong>
                Please correct the following:
            </strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif


    {{-- =========================================================
         STEP 1 — SELECT BRAND
    ========================================================== --}}
    <div class="card mb-4">

        <div class="card-header">

            <strong>
                Step 1 — Select Brand
            </strong>

        </div>

        <div class="card-body">

            <form
                method="POST"
                action="{{ route('vendor-inventory.preview') }}"
            >

                @csrf

                <div class="row align-items-end">

                    <div class="col-md-8">

                        <label
                            for="vendor"
                            class="form-label"
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

                            Example:
                            <strong>A.C. Grace</strong>

                        </div>

                    </div>


                    <div class="col-md-4">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >

                            Preview Inventory

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    {{-- =========================================================
         PREVIEW
    ========================================================== --}}
    @if(!empty($preview))

        <div class="card mb-4">

            <div class="card-header">

                <strong>
                    Step 2 — Inventory Preview
                </strong>

            </div>

            <div class="card-body">

                <div class="row g-4">

                    {{-- Brand --}}
                    <div class="col-md-3">

                        <strong>
                            Brand
                        </strong>

                        <div>
                            {{ $preview['vendor'] ?? '-' }}
                        </div>

                    </div>


                    {{-- Destination --}}
                    <div class="col-md-3">

                        <strong>
                            FSWarehouse
                        </strong>

                        <div>

                            {{ 
                                $preview['target_location']['name']
                                ?? $preview['fs_warehouse']['name']
                                ?? 'FSWarehouse'
                            }}

                        </div>

                    </div>


                    {{-- Products --}}
                    <div class="col-md-3">

                        <strong>
                            Products
                        </strong>

                        <div class="fs-5">

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

                        <strong>
                            Variants
                        </strong>

                        <div class="fs-5">

                            {{
                                number_format(
                                    $preview['summary']['variants']
                                    ?? $preview['variant_count']
                                    ?? count($preview['variants'] ?? [])
                                )
                            }}

                        </div>

                    </div>

                </div>


                <hr>


                <div class="row g-4">

                    {{-- Total Inventory --}}
                    <div class="col-md-4">

                        <strong>
                            Current Total Inventory
                        </strong>

                        <div class="fs-5">

                            {{
                                number_format(
                                    $preview['summary']['total_inventory']
                                    ?? $preview['total_inventory']
                                    ?? 0
                                )
                            }}

                        </div>

                    </div>


                    {{-- Current FS --}}
                    <div class="col-md-4">

                        <strong>
                            Current FSWarehouse Inventory
                        </strong>

                        <div class="fs-5">

                            {{
                                number_format(
                                    $preview['summary']['fswarehouse_current_inventory']
                                    ?? $preview['fswarehouse_current_inventory']
                                    ?? 0
                                )
                            }}

                        </div>

                    </div>


                    {{-- Inventory To Move --}}
                    <div class="col-md-4">

                        <strong>
                            Inventory Quantity
                        </strong>

                        <div class="fs-5">

                            <span class="badge text-bg-secondary">

                                No quantity changes

                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        {{-- =====================================================
             INVENTORY DETAILS
        ====================================================== --}}
        <div class="card mb-4">

            <div class="card-header">

                <strong>
                    Inventory Details
                </strong>

            </div>

            <div class="card-body p-0">

                @if(!empty($preview['variants']))

                    <div class="table-responsive">

                        <table
                            class="table table-bordered table-hover mb-0 align-middle"
                        >

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

                                    <th class="text-end">
                                        FSWarehouse
                                    </th>

                                    <th class="text-end">
                                        Total
                                    </th>

                                    <th>
                                        Locations
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @foreach(
                                    $preview['variants']
                                    as $row
                                )

                                    <tr>

                                        {{-- Product --}}
                                        <td>

                                            {{
                                                $row['product_title']
                                                ?? '-'
                                            }}

                                        </td>


                                        {{-- Variant --}}
                                        <td>

                                            {{
                                                $row['variant_title']
                                                ?? '-'
                                            }}

                                        </td>


                                        {{-- SKU --}}
                                        <td>

                                            <code>

                                                {{
                                                    $row['sku']
                                                    ?: '-'
                                                }}

                                            </code>

                                        </td>


                                        {{-- FSWarehouse --}}
                                        <td class="text-end">

                                            {{
                                                number_format(
                                                    $row['fs_quantity']
                                                    ?? 0
                                                )
                                            }}

                                        </td>


                                        {{-- Total --}}
                                        <td class="text-end">

                                            <strong>

                                                {{
                                                    number_format(
                                                        $row['total_quantity']
                                                        ?? 0
                                                    )
                                                }}

                                            </strong>

                                        </td>


                                        {{-- Locations --}}
                                        <td>

                                            @forelse(
                                                ($row['locations'] ?? [])
                                                as $location
                                            )

                                                <div class="mb-1">

                                                    <span>

                                                        {{
                                                            $location['location_name']
                                                            ?? 'Unknown'
                                                        }}

                                                    </span>

                                                    <span
                                                        class="badge
                                                        {{
                                                            ($location['is_active'] ?? false)
                                                                ? 'text-bg-success'
                                                                : 'text-bg-secondary'
                                                        }}"
                                                    >

                                                        {{
                                                            ($location['is_active'] ?? false)
                                                                ? 'Active'
                                                                : 'Inactive'
                                                        }}

                                                    </span>

                                                    <strong class="float-end">

                                                        {{
                                                            number_format(
                                                                $location['quantity']
                                                                ?? 0
                                                            )
                                                        }}

                                                    </strong>

                                                </div>

                                            @empty

                                                <span class="text-muted">
                                                    No locations found
                                                </span>

                                            @endforelse

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <div class="p-4 text-center text-muted">

                        No inventory variants were found
                        for this brand.

                    </div>

                @endif

            </div>

        </div>


        {{-- =====================================================
             STEP 3 — ACTIVATE FS-WAREHOUSE
        ====================================================== --}}
        @if(!empty($preview['variants']))

            <div class="card border-primary mb-4">

                <div class="card-header bg-primary-subtle">

                    <strong>
                        Step 3 — Activate FS-Warehouse
                    </strong>

                </div>

                <div class="card-body">

                    <p>
                        Activate
                        <strong>
                            {{
                                number_format(
                                    $preview['summary']['variants']
                                    ?? count($preview['variants'])
                                )
                            }}
                        </strong>
                        variants at
                        <strong>
                            {{
                                $preview['target_location']['name']
                                ?? 'FSWarehouse'
                            }}
                        </strong>.
                    </p>


                    <div class="alert alert-info">

                        <strong>
                            Important:
                        </strong>

                        <ul class="mb-0 mt-2">

                            <li>
                                Inventory quantities will not change.
                            </li>

                            <li>
                                Headquarters will remain active.
                            </li>

                            <li>
                                Other locations will remain unchanged.
                            </li>

                            <li>
                                Only FSWarehouse activation status
                                will be changed.
                            </li>

                        </ul>

                    </div>


                    <form
                        method="POST"
                        action="{{ route('vendor-inventory.activate') }}"
                        onsubmit="
                            return confirm(
                                'Activate FSWarehouse for all variants of this brand?'
                            );
                        "
                    >

                        @csrf

                        <input
                            type="hidden"
                            name="vendor"
                            value="{{ $preview['vendor'] ?? ($vendor ?? '') }}"
                        >

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            Activate FS-Warehouse

                        </button>

                    </form>

                </div>

            </div>

        @endif


        {{-- =====================================================
             ACTIVATION RESULT
        ====================================================== --}}
        @if(!empty($activationResult))

            <div class="card border-info mb-4">

                <div class="card-header bg-info-subtle">

                    <strong>
                        Activation Result
                    </strong>

                </div>

                <div class="card-body">

                    <div class="row g-3 mb-4">

                        <div class="col-md-4">

                            <div class="border rounded p-3">

                                <div class="text-muted">
                                    Total
                                </div>

                                <div class="fs-4">

                                    {{
                                        number_format(
                                            $activationResult['summary']['total']
                                            ?? 0
                                        )
                                    }}

                                </div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div
                                class="border border-success
                                rounded p-3"
                            >

                                <div class="text-muted">
                                    Activated
                                </div>

                                <div class="fs-4 text-success">

                                    {{
                                        number_format(
                                            $activationResult['summary']['successful']
                                            ?? 0
                                        )
                                    }}

                                </div>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div
                                class="border border-danger
                                rounded p-3"
                            >

                                <div class="text-muted">
                                    Failed
                                </div>

                                <div class="fs-4 text-danger">

                                    {{
                                        number_format(
                                            $activationResult['summary']['failed']
                                            ?? 0
                                        )
                                    }}

                                </div>

                            </div>

                        </div>

                    </div>


                    @if(
                        ($activationResult['summary']['failed'] ?? 0)
                        > 0
                    )

                        <div class="alert alert-danger">

                            Some variants failed to activate.

                            Please fix the errors before
                            continuing.

                        </div>

                    @else

                        <div class="alert alert-success">

                            All variants were successfully
                            activated at FSWarehouse.

                        </div>

                    @endif


                    {{-- Activation Details --}}
                    @if(!empty($activationResult['results']))

                        <div class="table-responsive mb-4">

                            <table class="table table-bordered">

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            SKU
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Variant
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

                                    @foreach(
                                        $activationResult['results']
                                        as $result
                                    )

                                        <tr>

                                            <td>

                                                <code>
                                                    {{
                                                        $result['sku']
                                                        ?? '-'
                                                    }}
                                                </code>

                                            </td>

                                            <td>

                                                {{
                                                    $result['product_title']
                                                    ?? '-'
                                                }}

                                            </td>

                                            <td>

                                                {{
                                                    $result['variant_title']
                                                    ?? '-'
                                                }}

                                            </td>

                                            <td>

                                                @if(
                                                    $result['success']
                                                    ?? false
                                                )

                                                    <span
                                                        class="badge text-bg-success"
                                                    >
                                                        Activated
                                                    </span>

                                                @else

                                                    <span
                                                        class="badge text-bg-danger"
                                                    >
                                                        Failed
                                                    </span>

                                                @endif

                                            </td>

                                            <td>

                                                {{
                                                    $result['message']
                                                    ?? ''
                                                }}

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @endif


                    {{-- Verify Activation --}}
                    @if(
                        ($activationResult['summary']['failed'] ?? 0)
                        === 0
                    )

                        <form
                            method="POST"
                            action="{{
                                route(
                                    'vendor-inventory.verify-activation'
                                )
                            }}"
                        >

                            @csrf

                            <input
                                type="hidden"
                                name="vendor"
                                value="{{
                                    $activationResult['vendor']
                                    ?? $vendor
                                }}"
                            >

                            <button
                                type="submit"
                                class="btn btn-info"
                            >

                                Verify Activation

                            </button>

                        </form>

                    @endif

                </div>

            </div>

        @endif


        {{-- =====================================================
             STEP 4 — ACTIVATION VERIFICATION
        ====================================================== --}}
        @if(!empty($activationVerification))

            <div class="card mb-4">

                <div class="card-header">

                    <strong>
                        Step 4 — Verify Activation
                    </strong>

                </div>

                <div class="card-body">

                    @if(
                        $activationVerification['verified']
                        ?? false
                    )

                        <div class="alert alert-success">

                            <strong>
                                Activation Verified
                            </strong>

                            <br>

                            All
                            {{
                                $activationVerification['summary']['verified']
                                ?? 0
                            }}
                            variants are active at
                            FSWarehouse.

                        </div>

                    @else

                        <div class="alert alert-danger">

                            <strong>
                                Activation Verification Failed
                            </strong>

                            <br>

                            Some variants are not active
                            at FSWarehouse.

                        </div>

                    @endif


                    {{-- Verification table --}}
                    @if(
                        !empty(
                            $activationVerification['results']
                        )
                    )

                        <div class="table-responsive mb-4">

                            <table class="table table-bordered">

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            SKU
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Variant
                                        </th>

                                        <th>
                                            FSWarehouse
                                        </th>

                                        <th>
                                            Quantity
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach(
                                        $activationVerification['results']
                                        as $result
                                    )

                                        <tr>

                                            <td>

                                                <code>
                                                    {{
                                                        $result['sku']
                                                        ?? '-'
                                                    }}
                                                </code>

                                            </td>

                                            <td>

                                                {{
                                                    $result['product_title']
                                                    ?? '-'
                                                }}

                                            </td>

                                            <td>

                                                {{
                                                    $result['variant_title']
                                                    ?? '-'
                                                }}

                                            </td>

                                            <td>

                                                @if(
                                                    $result['fs_active']
                                                    ?? false
                                                )

                                                    <span
                                                        class="badge text-bg-success"
                                                    >
                                                        Active
                                                    </span>

                                                @else

                                                    <span
                                                        class="badge text-bg-danger"
                                                    >
                                                        Not Active
                                                    </span>

                                                @endif

                                            </td>

                                            <td>

                                                {{
                                                    number_format(
                                                        $result['fs_quantity']
                                                        ?? 0
                                                    )
                                                }}

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @endif

                </div>

            </div>


            {{-- =================================================
                 STEP 5 — DEACTIVATE HQ
            ================================================== --}}
            @if(
                $activationVerification['verified']
                ?? false
            )

                <div class="card border-danger mb-4">

                    <div class="card-header bg-danger-subtle">

                        <strong>
                            Step 5 — Deactivate Headquarters
                        </strong>

                    </div>

                    <div class="card-body">

                        <div class="alert alert-warning">

                            <strong>
                                Final Step
                            </strong>

                            <br>

                            Activation has been verified.

                            <br><br>

                            The next action will deactivate
                            Headquarters and other active
                            inventory locations.

                            <br><br>

                            <strong>
                                FSWarehouse will remain active.
                            </strong>

                            <br>

                            Inventory quantities will NOT be changed.

                        </div>


                        <form
                            method="POST"
                            action="{{
                                route(
                                    'vendor-inventory.deactivate'
                                )
                            }}"
                            onsubmit="
                                return confirm(
                                    'FINAL STEP: Deactivate Headquarters and other inventory locations?'
                                );
                            "
                        >

                            @csrf

                            <input
                                type="hidden"
                                name="vendor"
                                value="{{
                                    $activationVerification['vendor']
                                    ?? $vendor
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

                                    I confirm that activation has
                                    been verified and I want to
                                    deactivate Headquarters.

                                </label>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-danger"
                            >

                                Deactivate HQ

                            </button>

                        </form>

                    </div>

                </div>

            @endif

        @endif


        {{-- =====================================================
             DEACTIVATION RESULT / COMPLETED
        ====================================================== --}}
        @if(!empty($deactivationResult))

            <div class="card border-success mb-4">

                <div class="card-header bg-success text-white">

                    <strong>
                        Completed
                    </strong>

                </div>

                <div class="card-body">

                    @if(
                        ($deactivationResult['summary']['failed'] ?? 0)
                        === 0
                    )

                        <div class="alert alert-success mb-0">

                            <h5 class="alert-heading">
                                Inventory Setup Completed
                            </h5>

                            <p class="mb-2">

                                All
                                {{
                                    $deactivationResult['summary']['successful']
                                    ?? 0
                                }}
                                variants were processed
                                successfully.

                            </p>

                            <hr>

                            <ul class="mb-0">

                                <li>
                                    FSWarehouse is active.
                                </li>

                                <li>
                                    Headquarters has been
                                    deactivated.
                                </li>

                                <li>
                                    Other inventory locations
                                    have been deactivated.
                                </li>

                                <li>
                                    Inventory quantities were
                                    <strong>not changed</strong>.
                                </li>

                            </ul>

                        </div>

                    @else

                        <div class="alert alert-warning">

                            <strong>
                                Completed with errors.
                            </strong>

                            <br><br>

                            Successful:
                            {{
                                $deactivationResult['summary']['successful']
                                ?? 0
                            }}

                            <br>

                            Failed:
                            {{
                                $deactivationResult['summary']['failed']
                                ?? 0
                            }}

                        </div>

                    @endif


                    {{-- Deactivation details --}}
                    @if(
                        !empty(
                            $deactivationResult['results']
                        )
                    )

                        <div class="table-responsive mt-4">

                            <table class="table table-bordered">

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            SKU
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Variant
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

                                    @foreach(
                                        $deactivationResult['results']
                                        as $result
                                    )

                                        <tr>

                                            <td>

                                                <code>
                                                    {{
                                                        $result['sku']
                                                        ?? '-'
                                                    }}
                                                </code>

                                            </td>

                                            <td>

                                                {{
                                                    $result['product_title']
                                                    ?? '-'
                                                }}

                                            </td>

                                            <td>

                                                {{
                                                    $result['variant_title']
                                                    ?? '-'
                                                }}

                                            </td>

                                            <td>

                                                @if(
                                                    $result['success']
                                                    ?? false
                                                )

                                                    <span
                                                        class="badge text-bg-success"
                                                    >
                                                        Completed
                                                    </span>

                                                @else

                                                    <span
                                                        class="badge text-bg-danger"
                                                    >
                                                        Failed
                                                    </span>

                                                @endif

                                            </td>

                                            <td>

                                                {{
                                                    $result['message']
                                                    ?? ''
                                                }}

                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @endif

                </div>

            </div>

        @endif

    @endif

</div>

@endsection