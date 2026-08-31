<?php

namespace App\Jobs;

use App\Services\ProductSync\ProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncFullscriptProductJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public array $backoff = [
        10,
        30,
        60,
        120,
        300,
    ];

    public function __construct(
        public array $product
    ) {
    }

    public function handle(
        ProductSyncService $syncService
    ): void {

        $syncService->sync(
            $this->product
        );
    }

    public function failed(
        Throwable $exception
    ): void {

        logger()->error(
            'Fullscript product job failed permanently.',
            [
                'product' =>
                    $this->product,

                'error' =>
                    $exception->getMessage(),
            ]
        );
    }
}