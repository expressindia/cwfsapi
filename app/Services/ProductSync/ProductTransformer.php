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

        $variants = $this->transformVariants( $product['variants'] ?? [] );

        
        return [
            'fullscript_product_id' => $productId,
            'title'                 => $name,
            'description_html'      => $product['description_html'] ?? '',
            'vendor'                => $product['brand']['name'] ?? 'Fullscript',
            'product_type'          => 'Supplement',

            // Do not automatically archive Shopify products.
            'status' => ($product['status'] ?? '') === 'available' ? 'ACTIVE' : 'ARCHIVED',

            'handle'                => Str::slug(($product['brand']['name'] ?? 'fullscript') . '-' . $name . '-' . $productId ),

            'product_options' => [
                [
                    'name' => 'Size',
                    'position' => 1,
                    'values' => $this->transformOptionValues($variants),
                ],
            ],

            'variants' => $variants,
            /*
             * Fullscript provides small, medium and large images.
             * We use the large image for Shopify.
             */
            'images' => $this->transformImages($product),
        ]; 
    }



    /**
     * Transform Fullscript variants.
     */
    protected function transformVariants(array $variants): array
    {
        if (empty($variants)) {
            throw new RuntimeException(
                'Fullscript product has no variants.'
            );
        }

        $result = [];
        $seenSkus = [];
        $seenOptionValues = [];

        foreach ($variants as $variant) {
            $variantId = $variant['id'] ?? null;

            $sku = trim(
                (string) ($variant['sku'] ?? '')
            );

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

            /*
             * Fullscript "units" means package content,
             * NOT inventory quantity.
             *
             * Example:
             * units = 200
             * unit_of_measure = Softgels
             *
             * Shopify option value:
             * 200 Softgels
             */
            $optionValue = $this->buildOptionValue($variant);

            if (isset($seenOptionValues[$optionValue])) {
                throw new RuntimeException(
                    "Duplicate Size option value '{$optionValue}' found in Fullscript product."
                );
            }

            $seenOptionValues[$optionValue] = true;

            $result[] = [
                'fullscript_variant_id' => $variantId,

                'sku' => $sku,

                'price' => (string) (
                    $variant['msrp'] ?? '0.00'
                ),

                'barcode' => $variant['upc'] ?? null,

                /*
                 * Package information.
                 *
                 * IMPORTANT:
                 * This is NOT inventory quantity.
                 */
                'units' => $variant['units'] ?? null,

                'unit_of_measure' => $variant['unit_of_measure'] ?? null,

                'availability' => $variant['availability'] ?? null,

                'status' => $variant['status'] ?? null,

                'supplier_sku' => $variant['supplier_sku'] ?? null,

                'primary' => (bool) (
                    $variant['primary'] ?? false
                ),

                /*
                 * We currently do not have actual inventory
                 * quantity from the Fullscript API.
                 */
                // Fullscript does not provide inventory quantity here.
                'quantity' => ($variant['availability'] ?? '') === 'In Stock' ? 55 : 0,

                /*
                 * Shopify option value.
                 */
                'option_name' => 'Size',

                'option_value' => $optionValue,
            ];
        }

        return $result;
    }


    /**
     * Generate a unique Shopify handle.
     *
     * Example:
     * physiologics-b-1-thiamine-hcl-250-mg-100-vtabs-{product-id}
     */
    protected function generateHandle(array $product): string
    {
        $brand = $product['brand']['name'] ?? 'Fullscript';
        $name = $product['name'] ?? '';
        $productId = $product['id'] ?? '';

        return Str::slug(
            $brand . '-' . $name . '-' . $productId
        );
    }

    /**
     * Build the Shopify Size option value.
     *
     * Examples:
     *
     * 200 + Softgels => 200 Softgels
     * 90 + Softgels  => 90 Softgels
     * 50 + gels      => 50 gels
     */
    protected function buildOptionValue(array $variant): string
{
    $units = $variant['units'] ?? null;

    $unitOfMeasure = trim(
        (string) ($variant['unit_of_measure'] ?? '')
    );

    if (
        $units !== null &&
        $units !== '' &&
        (int) $units > 0 &&
        $unitOfMeasure !== ''
    ) {
        return trim(
            (string) $units . ' ' . $unitOfMeasure
        );
    }

    if (
        $units !== null &&
        $units !== '' &&
        (int) $units > 0
    ) {
        return (string) $units;
    }

    if ($unitOfMeasure !== '') {
        return $unitOfMeasure;
    }

    return 'Default';
}

    /**
     * Build Shopify product option values.
     */
    protected function transformOptionValues(
        array $variants
    ): array {
        $values = [];
        $seen = [];

        foreach ($variants as $variant) {
            $value = $variant['option_value'] ?? null;

            if (!$value) {
                continue;
            }

            if (isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;

            $values[] = [
                'name' => $value,
            ];
        }

        if (empty($values)) {
            $values[] = [
                'name' => 'Default',
            ];
        }

        return $values;
    }

    /**
     * Transform Fullscript product images.
     *
     * Fullscript provides:
     * - image_url_small
     * - image_url_medium
     * - image_url_large
     *
     * We import the large image into Shopify.
     */
    protected function transformImages(array $product): array
    {
        $imageUrl = $product['image_url_large'] ?? null;

        if (!$imageUrl) {
            return [];
        }

        return [
            [
                'url' => $imageUrl,
                'alt' => $product['name'] ?? 'Fullscript product',
            ],
        ];
    }
}