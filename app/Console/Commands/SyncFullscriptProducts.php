<?php

namespace App\Console\Commands;

use App\Jobs\SyncFullscriptProductJob;
use App\Services\Fullscript\FullscriptProductService;
use Illuminate\Console\Command;
use Throwable;

class SyncFullscriptProducts extends Command
{
    protected $signature =
        'fullscript:products-sync
        {--page=1}
        {--per-page=100}';

    protected $description =
        'Queue Fullscript products for Shopify synchronization';

    public function handle(
        FullscriptProductService $fullscript
    ): int {

        $page =
            (int) $this->option(
                'page'
            );

        $perPage =
            (int) $this->option(
                'per-page'
            );

        try {

            $this->info(
                "Fetching Fullscript page {$page}..."
            );

            $response =
                $fullscript->getProducts(
                    $page,
                    $perPage
                );

        } catch (Throwable $e) {

            $this->error(
                $e->getMessage()
            );

            return self::FAILURE;
        }

        $products =
            $response['products']
            ?? $response['data']
            ?? [];

        if (
            empty($products)
        ) {

            $this->info(
                'No products returned.'
            );

            return self::SUCCESS;
        }

        foreach (
            $products as $product
        ) {

            SyncFullscriptProductJob::dispatch(
                $product
            );
        }

        $this->info(
            count($products) .
            ' products queued.'
        );

        return self::SUCCESS;
    }
}