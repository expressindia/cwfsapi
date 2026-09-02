<?php

namespace App\Services\ProductSync;

use Illuminate\Support\Str;
use RuntimeException;

class ProductTransformer
{
    public function transform(array $product): array
    {

        $productId = $product['id'] ?? null;
        $name = $product['name'] ?? null;
        
        if (!$productId) {
            throw new RuntimeException(
                'Fullscript product ID is missing.'
            );
        }

        if (!$name) {
            throw new RuntimeException(
                "Product {$productId} has no name."
            );
        }

        return [
            'fullscript_product_id' => $productId,
            'title'                 => $name,
            'description_html'      => $product['description_html'] ?? '',
            'vendor'                => $product['brand']['name'] ?? 'Fullscript',
            'product_type'          => 'Supplement',

            // Do not automatically archive Shopify products.
            'status'                => 'ACTIVE',
            'handle'                => Str::slug(($product['brand']['name'] ?? 'fullscript') . '-' . $name . '-' . $productId ),

            'variants'              => $this->transformVariants( $product['variants'] ?? [] ),
        ]; 
    }

    protected function transformVariants(array $variants): array
    {
        if (empty($variants)) {
            throw new RuntimeException(
                'Fullscript product has no variants.'
            );
        }

        $result = [];
        $seenSkus = [];

        foreach ($variants as $variant) {

            $sku = trim( (string) ($variant['sku'] ?? '') );

            if ($sku === '') {
                throw new RuntimeException(
                    'Fullscript variant is missing SKU.'
                );
            }

            if (isset($seenSkus[$sku])) {
                throw new RuntimeException(
                    "Duplicate SKU {$sku} found in Fullscript product."
                );
            }

            $seenSkus[$sku] = true;

            $result[] = [ 
                
                'fullscript_variant_id' => $variant['id'] ?? null,

                'sku' => $sku,

                'price' => (string) ($variant['msrp'] ?? '0.00'),

                'barcode' => $variant['upc'] ?? null,

                'units' => $variant['units'] ?? null,

                'unit_of_measure' => $variant['unit_of_measure'] ?? null,

                'availability' => $variant['availability'] ?? null,

                'status' => $variant['status'] ?? null,

                'supplier_sku' => $variant['supplier_sku'] ?? null,

                'primary' => (bool) ($variant['primary'] ?? false),

                // Fullscript does not provide inventory quantity here.
                'quantity' => null,
            ];
        }

        return $result;
    }
}