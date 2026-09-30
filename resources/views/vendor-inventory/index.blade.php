@extends('layouts.app')

@section('content')

<div class="container py-4">

    <div class="card shadow-sm">

        <div class="card-header">
            <h4 class="mb-0">
                Vendor Inventory
            </h4>
        </div>

        <div class="card-body">

            {{-- Success message --}}
            @if(session('result'))

                <div class="alert alert-success">
                    <strong>Success!</strong>

                    @if(is_array(session('result')))

                        <pre class="mb-0 mt-2">{{ json_encode(session('result'), JSON_PRETTY_PRINT) }}</pre>

                    @else

                        {{ session('result') }}

                    @endif
                </div>

            @endif


            {{-- Validation / error messages --}}
            @if($errors->any())

                <div class="alert alert-danger">

                    <strong>Please fix the following:</strong>

                    <ul class="mb-0 mt-2">

                        @foreach($errors->all() as $error)

                            <li>
                                {{ $error }}
                            </li>

                        @endforeach

                    </ul>

                </div>

            @endif


            {{-- Preview Form --}}
            <form
                method="POST"
                action="{{ route('vendor-inventory.preview') }}"
            >

                @csrf

                <div class="mb-3">

                    <label
                        for="vendor"
                        class="form-label"
                    >
                        Vendor
                    </label>

                    <input
                        type="text"
                        id="vendor"
                        name="vendor"
                        class="form-control"
                        value="{{ old('vendor', $vendor ?? '') }}"
                        placeholder="Enter vendor name"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Preview Inventory
                </button>

            </form>


            {{-- Preview Result --}}
            @if(!empty($preview))

                <hr class="my-4">

                <h5 class="mb-3">
                    Inventory Preview
                </h5>


                <div class="row">

                    <div class="col-md-6">

                        <div class="card border">

                            <div class="card-body">

                                <h6 class="text-muted">
                                    Vendor
                                </h6>

                                <h5>
                                    {{ $preview['vendor'] ?? ($vendor ?? '') }}
                                </h5>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="card border">

                            <div class="card-body">

                                <h6 class="text-muted">
                                    FSWarehouse
                                </h6>

                                <h5>
                                    {{ $preview['fs_quantity'] ?? 0 }}
                                </h5>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="row mt-3">

                    <div class="col-md-6">

                        <div class="card border">

                            <div class="card-body">

                                <h6 class="text-muted">
                                    Total Inventory
                                </h6>

                                <h5>
                                    {{ $preview['total_quantity'] ?? 0 }}
                                </h5>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="card border">

                            <div class="card-body">

                                <h6 class="text-muted">
                                    New FSWarehouse Quantity
                                </h6>

                                <h5>
                                    {{ $preview['new_fs_quantity'] ?? 0 }}
                                </h5>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- Locations --}}
                @if(!empty($preview['locations']))

                    <div class="mt-4">

                        <h6>
                            Inventory Locations
                        </h6>

                        <div class="table-responsive">

                            <table class="table table-bordered">

                                <thead>

                                    <tr>

                                        <th>
                                            Location
                                        </th>

                                        <th>
                                            Quantity
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    @foreach(
                                        $preview['locations']
                                        as $location
                                    )

                                        <tr>

                                            <td>
                                                {{ $location['name'] ?? '' }}
                                            </td>

                                            <td>
                                                {{ $location['quantity'] ?? 0 }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    </div>

                @endif


                {{-- Move Inventory --}}
                <div class="mt-4">

                    <form
                        method="POST"
                        action="{{ route('vendor-inventory.move') }}"
                        onsubmit="return confirm(
                            'Are you sure you want to move all inventory for this vendor to FSWarehouse?'
                        );"
                    >

                        @csrf

                        {{-- Vendor --}}
                        <input
                            type="hidden"
                            name="vendor"
                            value="{{ $preview['vendor'] ?? ($vendor ?? '') }}"
                        >

                        {{-- Required by controller --}}
                        <input
                            type="hidden"
                            name="confirm"
                            value="1"
                        >

                        <button
                            type="submit"
                            class="btn btn-warning"
                        >
                            Move Inventory to FSWarehouse
                        </button>

                    </form>

                </div>

            @endif

        </div>

    </div>

</div>

@endsection