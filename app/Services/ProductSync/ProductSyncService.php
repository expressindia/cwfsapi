<?php

namespace App\Services\ProductSync;

use App\Models\ProductSync;
use App\Models\ProductVariant;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProductSyncService
{
    public function __construct(
        protected ProductTransformer $transformer,
        protected ShopifyProductService $shopify
    ) {
    }

    public function sync(
        array $fullscriptProduct
    ): ProductSync {

        $product =
            $this->transformer->transform(
                $fullscriptProduct
            );

        $fullscriptProductId =
            $product[
                'fullscript_product_id'
            ];

        $sync =
            ProductSync::firstOrCreate(
                [
                    'fullscript_product_id' =>
                        $fullscriptProductId,
                ],
                [
                    'status' =>
                        'pending',
                ]
            );

        try {

            $sync->increment(
                'sync_attempts'
            );

            $sync->update([
                'status' =>
                    'syncing',

                'last_error' =>
                    null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | STEP 1
            | Look for existing Shopify product using SKU.
            |--------------------------------------------------------------------------
            */

            $existingShopifyProduct =
                $this->findExistingProduct(
                    $product
                );

            /*
            |--------------------------------------------------------------------------
            | STEP 2
            | If SKU matches an existing Shopify product,
            | update that product.
            |--------------------------------------------------------------------------
            */

            if (
                $existingShopifyProduct
            ) {

                $shopifyProductId =
                    $existingShopifyProduct[
                        'product'
                    ]['id'];

                $shopifyProduct =
                    $this->shopify->createOrUpdate(
                        $product,
                        $shopifyProductId
                    );

            } else {

                /*
                |--------------------------------------------------------------------------
                | No matching SKU.
                |
                | We create a new Shopify product.
                |--------------------------------------------------------------------------
                */

                $shopifyProduct =
                    $this->shopify->createOrUpdate(
                        $product
                    );
            }

            DB::transaction(
                function () use (
                    $sync,
                    $product,
                    $shopifyProduct
                ) {

                    $sync->update([

                        'shopify_product_id' =>
                            $shopifyProduct['id'],

                        'shopify_handle' =>
                            $shopifyProduct[
                                'handle'
                            ] ?? null,

                        'shopify_product_status' =>
                            $shopifyProduct[
                                'status'
                            ] ?? null,

                        'status' =>
                            'synced',

                        'last_synced_at' =>
                            now(),

                        'last_error' =>
                            null,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Shopify variants indexed by SKU
                    |--------------------------------------------------------------------------
                    */

                    $shopifyBySku =
                        [];

                    $shopifyVariants =
                        $shopifyProduct[
                            'variants'
                        ]['nodes'] ?? [];

                    foreach (
                        $shopifyVariants
                        as $shopifyVariant
                    ) {

                        $sku =
                            trim(
                                (string) (
                                    $shopifyVariant[
                                        'sku'
                                    ] ?? ''
                                )
                            );

                        if ($sku === '') {
                            continue;
                        }

                        if (
                            isset(
                                $shopifyBySku[
                                    $sku
                                ]
                            )
                        ) {

                            throw new RuntimeException(
                                "Duplicate Shopify SKU detected: {$sku}"
                            );
                        }

                        $shopifyBySku[$sku] =
                            $shopifyVariant;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Map Fullscript variants
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $product['variants']
                        as $variant
                    ) {

                        $sku =
                            $variant['sku'];

                        if (
                            !isset(
                                $shopifyBySku[
                                    $sku
                                ]
                            )
                        ) {

                            throw new RuntimeException(
                                "Shopify variant not found for SKU: {$sku}"
                            );
                        }

                        $shopifyVariant =
                            $shopifyBySku[
                                $sku
                            ];

                        ProductVariant::
                            updateOrCreate(
                                [
                                    'sku' =>
                                        $sku,
                                ],
                                [

                                    'fullscript_product_id' =>
                                        $product[
                                            'fullscript_product_id'
                                        ],

                                    'fullscript_variant_id' =>
                                        $variant[
                                            'fullscript_variant_id'
                                        ],

                                    'shopify_product_id' =>
                                        $shopifyProduct[
                                            'id'
                                        ],

                                    'shopify_product_status' =>
                                        $shopifyProduct[
                                            'status'
                                        ] ?? null,

                                    'shopify_variant_id' =>
                                        $shopifyVariant[
                                            'id'
                                        ],

                                    'shopify_inventory_item_id' =>
                                        $shopifyVariant[
                                            'inventoryItem'
                                        ]['id'] ?? null,

                                    'fullscript_quantity' =>
                                        $variant[
                                            'quantity'
                                        ],

                                    'status' =>
                                        'synced',

                                    'sync_attempts' =>
                                        0,

                                    'last_error' =>
                                        null,

                                    'last_synced_at' =>
                                        now(),
                                ]
                            );
                    }
                }
            );

            return $sync->fresh();

        } catch (Throwable $e) {

            $sync->update([
                'status' =>
                    'failed',

                'last_error' =>
                    $e->getMessage(),
            ]);

            Log::error(
                'Fullscript product synchronization failed.',
                [
                    'fullscript_product_id' =>
                        $fullscriptProductId,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    protected function findExistingProduct(
        array $product
    ): ?array {

        foreach (
            $product['variants']
            as $variant
        ) {

            $sku =
                $variant['sku'];

            $existing =
                $this->shopify->findProductBySku(
                    $sku
                );

            if ($existing) {

                return $existing;
            }
        }

        return null;
    }
}