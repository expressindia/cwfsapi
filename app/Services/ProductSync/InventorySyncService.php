<?php

namespace App\Services\ProductSync;

use App\Models\ProductVariant;
use App\Services\Shopify\ShopifyInventoryService;
use RuntimeException;

class InventorySyncService
{
    public function __construct(
        protected ShopifyInventoryService $shopifyInventory
    ) {
    }

    public function syncBySku(
        string $sku,
        int $quantity
    ): ?ProductVariant {

        $sku =
            trim($sku);

        if ($sku === '') {
            throw new RuntimeException(
                'SKU cannot be empty.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find our SKU mapping
        |--------------------------------------------------------------------------
        */

        $variant =
            ProductVariant::where(
                'sku',
                $sku
            )->first();

        if (!$variant) {

            throw new RuntimeException(
                "No mapping exists for SKU: {$sku}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |
        | Inventory can ONLY be updated when Shopify
        | product status is ACTIVE.
        |--------------------------------------------------------------------------
        */

        if (
            strtoupper(
                (string)
                    $variant
                        ->shopify_product_status
            ) !== 'ACTIVE'
        ) {

            $variant->update([

                'status' =>
                    'skipped',

                'last_error' =>
                    'Inventory update skipped because Shopify product is not ACTIVE.',
            ]);

            return $variant->fresh();
        }

        /*
        |--------------------------------------------------------------------------
        | Inventory Item
        |--------------------------------------------------------------------------
        */

        if (
            !$variant
                ->shopify_inventory_item_id
        ) {

            throw new RuntimeException(
                "Inventory item missing for SKU: {$sku}"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Set exact quantity
        |--------------------------------------------------------------------------
        */

        $this->shopifyInventory
            ->setQuantity(
                $variant
                    ->shopify_inventory_item_id,
                $quantity
            );

        /*
        |--------------------------------------------------------------------------
        | Update local state
        |--------------------------------------------------------------------------
        */

        $variant->update([

            'fullscript_quantity' =>
                $quantity,

            'shopify_quantity' =>
                $quantity,

            'status' =>
                'synced',

            'last_synced_at' =>
                now(),

            'last_error' =>
                null,
        ]);

        return $variant->fresh();
    }
}