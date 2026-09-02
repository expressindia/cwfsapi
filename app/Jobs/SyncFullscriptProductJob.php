<?php

namespace App\Jobs;

use App\Services\Fullscript\FullscriptProductService;
use App\Services\ProductSync\ProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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

    public function __construct( public string $productId ) {
    }

    public function handle( FullscriptProductService $fullscript, ProductSyncService $syncService ): void 
    {
        //$syncService->sync( $this->product );

        // Get complete product from Fullscript
        $response = $fullscript->getProduct( $this->productId );
        // Fullscript detail response:
        // {
        //     "product": {...}
        // }
        $product = $response['product'] ?? null;

        if (!$product) {
            throw new \RuntimeException(
                "Fullscript product {$this->productId} not found."
            );
        }
        // Now send complete product to sync service
        $syncService->sync($product);
    }

    public function failed( Throwable $exception ): void 
    { 
        logger()->error(
            'Fullscript product job failed permanently.',
            [
                'product_id' => $this->productId,
                'error' => $exception->getMessage(),
            ]
        );
    }
}