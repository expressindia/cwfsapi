@extends('layouts.app')

@section('content')

<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h1 class="text-2xl font-semibold text-gray-900">
                Shopify
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Manage your Shopify connection and fulfillment service.
            </p>
        </div>

        @if(($status['connected'] ?? false))
            <span class="inline-flex items-center gap-2 rounded-full bg-green-100 px-3 py-1.5 text-sm font-medium text-green-700">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                Connected
            </span>
        @else
            <span class="inline-flex items-center gap-2 rounded-full bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                Not Connected
            </span>
        @endif

    </div>


    {{-- Success Message --}}
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif


    {{-- Error Message --}}
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif


    {{-- Page Load Error --}}
    @if(isset($error))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $error }}
        </div>
    @endif


    {{-- Shopify Connection --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

        <div class="border-b border-gray-200 px-6 py-5">

            <div class="flex items-center justify-between">

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Shopify Connection
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Current Shopify store connection details.
                    </p>
                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                    <svg
                        class="h-5 w-5 text-green-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>

            </div>

        </div>


        <div class="grid grid-cols-1 gap-6 px-6 py-6 md:grid-cols-2">

            <div>
                <p class="text-sm text-gray-500">
                    Connection Status
                </p>

                <p class="mt-1 text-sm font-medium text-green-600">
                    Connected
                </p>
            </div>


            <div>
                <p class="text-sm text-gray-500">
                    Store
                </p>

                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ $status['shop_name'] ?? 'N/A' }}
                </p>
            </div>


            <div>
                <p class="text-sm text-gray-500">
                    Store Domain
                </p>

                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ $status['shop_domain'] ?? 'N/A' }}
                </p>
            </div>


            <div>
                <p class="text-sm text-gray-500">
                    API Version
                </p>

                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ config('shopify.api_version', '2026-07') }}
                </p>
            </div>

        </div>

    </div>


    {{-- Fulfillment Service --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

        <div class="border-b border-gray-200 px-6 py-5">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Fulfillment Service
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Manage the FSWarehouse fulfillment service for this Shopify store.
                    </p>
                </div>


                @if(!empty($status['service']))

                    <span class="inline-flex items-center gap-2 rounded-full bg-green-100 px-3 py-1.5 text-sm font-medium text-green-700">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        Registered
                    </span>

                @else

                    <span class="inline-flex items-center gap-2 rounded-full bg-yellow-100 px-3 py-1.5 text-sm font-medium text-yellow-700">
                        <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                        Not Registered
                    </span>

                @endif

            </div>

        </div>


        <div class="px-6 py-6">

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

                <div>
                    <p class="text-sm text-gray-500">
                        Service Name
                    </p>

                    <p class="mt-1 text-sm font-medium text-gray-900">
                        {{ $status['service_name'] ?? 'FSWarehouse' }}
                    </p>
                </div>


                <div>
                    <p class="text-sm text-gray-500">
                        Service Status
                    </p>

                    @if(!empty($status['service']))

                        <p class="mt-1 text-sm font-medium text-green-600">
                            Registered
                        </p>

                    @else

                        <p class="mt-1 text-sm font-medium text-yellow-600">
                            Not Registered
                        </p>

                    @endif
                </div>


                @if(!empty($status['service']))

                    <div>
                        <p class="text-sm text-gray-500">
                            Fulfillment Service ID
                        </p>

                        <p class="mt-1 break-all text-sm font-medium text-gray-900">
                            {{ $status['service']['id'] ?? 'N/A' }}
                        </p>
                    </div>


                    <div>
                        <p class="text-sm text-gray-500">
                            Location
                        </p>

                        <p class="mt-1 text-sm font-medium text-gray-900">
                            {{ $status['service']['location']['name'] ?? 'N/A' }}
                        </p>
                    </div>


                    <div>
                        <p class="text-sm text-gray-500">
                            Location ID
                        </p>

                        <p class="mt-1 break-all text-sm font-medium text-gray-900">
                            {{ $status['service']['location']['id'] ?? 'N/A' }}
                        </p>
                    </div>


                    <div>
                        <p class="text-sm text-gray-500">
                            Tracking Support
                        </p>

                        <p class="mt-1 text-sm font-medium text-green-600">
                            {{ !empty($status['service']['trackingSupport']) ? 'Enabled' : 'Disabled' }}
                        </p>
                    </div>

                @endif

            </div>


            {{-- Register Button --}}
            @if(empty($status['service']))

                <div class="mt-8 border-t border-gray-200 pt-6">

                    <form
                        method="POST"
                        action="{{ route('shopify.fulfillment.register') }}"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="inline-flex items-center rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            Register FSWarehouse
                        </button>

                    </form>

                    <p class="mt-2 text-xs text-gray-500">
                        Shopify will create the FSWarehouse fulfillment service and its associated location.
                    </p>

                </div>

            @else

                <div class="mt-8 border-t border-gray-200 pt-6">

                    <div class="flex items-center gap-2 text-sm text-green-600">

                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>

                        <span>
                            FSWarehouse is registered for this Shopify store.
                        </span>

                    </div>

                </div>

            @endif

        </div>

    </div>

</div>

@endsection