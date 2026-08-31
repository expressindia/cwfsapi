<?php

namespace App\Services\ProductSync;

use Illuminate\Support\Str;
use RuntimeException;

class ProductTransformer
{
    public function transform(
        array $fullscriptProduct
    ): array {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        | These field names must match your actual Fullscript response.
        |--------------------------------------------------------------------------
        */

        $fullscriptProductId =
            (string) (
                $fullscriptProduct['id']
                ?? $fullscriptProduct['product_id']
                ?? ''
            );

        if (!$fullscriptProductId) {
            throw new RuntimeException(
                'Fullscript product ID is missing.'
            );
        }

        $name =
            $fullscriptProduct['name']
            ?? $fullscriptProduct['title']
            ?? '';

        if (!$name) {
            throw new RuntimeException(
                "Product {$fullscriptProductId} has no name."
            );
        }

        return [

            'fullscript_product_id' =>
                $fullscriptProductId,

            'title' =>
                $name,

            'description_html' =>
                $fullscriptProduct[
                    'description'
                ] ?? '',

            'vendor' =>
                $fullscriptProduct[
                    'brand'
                ] ?? 'Fullscript',

            'product_type' =>
                $fullscriptProduct[
                    'product_type'
                ] ?? 'Supplement',

            /*
            |--------------------------------------------------------------------------
            | Don't automatically archive products based on Fullscript status.
            |--------------------------------------------------------------------------
            */

            'status' =>
                'ACTIVE',

            'handle' =>
                Str::slug($name),

            'variants' =>
                $this->transformVariants(
                    $fullscriptProduct
                ),
        ];
    }

    protected function transformVariants(
        array $product
    ): array {

        $variants =
            $product['variants'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Single variant product
        |--------------------------------------------------------------------------
        */

        if (empty($variants)) {

            $variants = [
                $product,
            ];
        }

        $result = [];

        $seenSkus = [];

        foreach ($variants as $variant) {

            $sku =
                trim(
                    (string) (
                        $variant['sku']
                        ?? ''
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | SKU is mandatory
            |--------------------------------------------------------------------------
            */

            if ($sku === '') {

                throw new RuntimeException(
                    'Fullscript variant is missing SKU.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Duplicate SKU inside same product
            |--------------------------------------------------------------------------
            */

            if (
                isset(
                    $seenSkus[$sku]
                )
            ) {

                throw new RuntimeException(
                    "Duplicate SKU {$sku} found in Fullscript product."
                );
            }

            $seenSkus[$sku] = true;

            $result[] = [

                'fullscript_variant_id' =>
                    isset(
                        $variant['id']
                    )
                        ? (string)
                            $variant['id']
                        : null,

                'sku' =>
                    $sku,

                'price' =>
                    (string) (
                        $variant['price']
                        ?? '0.00'
                    ),

                'compare_at_price' =>
                    isset(
                        $variant[
                            'compare_at_price'
                        ]
                    )
                        ? (string)
                            $variant[
                                'compare_at_price'
                            ]
                        : null,

                'barcode' =>
                    $variant[
                        'barcode'
                    ] ?? null,

                'quantity' =>
                    isset(
                        $variant[
                            'quantity'
                        ]
                    )
                        ? (int)
                            $variant[
                                'quantity'
                            ]
                        : null,
            ];
        }

        return $result;
    }
}