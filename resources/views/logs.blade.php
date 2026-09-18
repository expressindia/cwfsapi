@extends('layouts.app')

@section('title', 'Application Logs - CWFSAPI')

@section('page-title', 'Application Logs')

@section('content')

<div class="container-fluid px-0">

    {{-- Page Heading --}}
    <div class="mb-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

            <div>
                <h3 class="fw-semibold mb-1">
                    Application Logs
                </h3>

                <p class="text-muted mb-0">
                    View application errors and integration activity.
                </p>
            </div>

            <div class="d-flex gap-2">

                {{-- Refresh --}}
                <a href="{{ route('logs') }}"
                   class="btn btn-outline-secondary">

                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Refresh

                </a>


                {{-- Clear --}}
                <form action="{{ route('logs.clear') }}"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('Are you sure you want to clear all application logs?');">

                    @csrf

                    <button type="submit"
                            class="btn btn-outline-danger">

                        <i class="bi bi-trash me-1"></i>
                        Clear Logs

                    </button>

                </form>

            </div>

        </div>

    </div>


    {{-- Success Message --}}
    @if(session('success'))

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle me-2"></i>

            {{ session('success') }}

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
            </button>

        </div>

    @endif


    {{-- Logs --}}
    <div class="card dashboard-card">

        <div class="card-body p-0">

            {{-- Header --}}
            <div class="p-4 border-bottom">

                <div class="d-flex align-items-center">

                    <div class="status-icon danger me-3">

                        <i class="bi bi-terminal"></i>

                    </div>

                    <div>

                        <h5 class="mb-1">
                            Application Logs
                        </h5>

                        <small class="text-muted">
                            {{ count($logs) }} log entries
                        </small>

                    </div>

                </div>

            </div>


            {{-- Log List --}}
            <div class="p-4">

                @forelse($logs as $index => $log)

                    @php

                        $level = strtolower($log['level']);

                        $badgeClass = match($level) {

                            'error',
                            'critical',
                            'alert',
                            'emergency'
                                => 'bg-danger-subtle text-danger',

                            'warning'
                                => 'bg-warning-subtle text-warning-emphasis',

                            'notice'
                                => 'bg-info-subtle text-info-emphasis',

                            'debug'
                                => 'bg-secondary-subtle text-secondary',

                            default
                                => 'bg-success-subtle text-success',

                        };

                        $icon = match($level) {

                            'error',
                            'critical',
                            'alert',
                            'emergency'
                                => 'bi-exclamation-triangle',

                            'warning'
                                => 'bi-exclamation-circle',

                            'notice'
                                => 'bi-info-circle',

                            'debug'
                                => 'bi-bug',

                            default
                                => 'bi-check-circle',

                        };

                    @endphp


                    <div class="log-entry mb-3">

                        {{-- Log Header --}}
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">

                            <div class="d-flex align-items-center gap-2">

                                <span class="badge {{ $badgeClass }} px-3 py-2">

                                    <i class="bi {{ $icon }} me-1"></i>

                                    {{ $log['level'] }}

                                </span>

                                <small class="text-muted">

                                    <i class="bi bi-clock me-1"></i>

                                    {{ \Carbon\Carbon::parse($log['datetime'])->format('M d, Y - h:i A') }}

                                </small>

                            </div>

                        </div>


                        {{-- Log Body --}}
                        <div class="log-message">

                            {!! nl2br(e($log['message'])) !!}

                        </div>

                    </div>

                @empty

                    <div class="text-center py-5">

                        <i class="bi bi-file-earmark-check text-muted"
                           style="font-size: 3rem;">
                        </i>

                        <h5 class="fw-semibold mt-3">
                            No logs available
                        </h5>

                        <p class="text-muted mb-0">
                            There are currently no entries in the application log.
                        </p>

                    </div>

                @endforelse

            </div>

        </div>

    </div>

</div>


@push('styles')

<style>

    .log-entry {
        padding: 18px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
        transition: box-shadow 0.2s ease;
    }

    .log-entry:hover {
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
    }

    .log-message {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 14px 16px;

        font-family:
            SFMono-Regular,
            Menlo,
            Monaco,
            Consolas,
            "Liberation Mono",
            "Courier New",
            monospace;

        font-size: 13px;
        line-height: 1.6;

        white-space: pre-wrap;
        word-break: break-word;

        color: #343a40;
    }

</style>

@endpush

@endsection