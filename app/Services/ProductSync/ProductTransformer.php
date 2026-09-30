<?php

namespace App\Services\ProductSync;

use Illuminate\Support\Str;
use RuntimeException;

class ProductTransformer
{
    public function transform(array $product): array
    {
        $productId = $product['id'] ?? null;
        $name = trim((string) ($product['name'] ?? ''));

        if (!$productId) {
            throw new RuntimeException(
                'Fullscript product ID is missing.'
            );
        }

        if ($name === '') {
            throw new RuntimeException(
                "Product {$productId} has no name."
            );
        }

        $vendor = trim(
            (string) ($product['brand']['name'] ?? 'Fullscript')
        );

        $variants = $this->transformVariants(
            $product['variants'] ?? []
        );

        /*
        |--------------------------------------------------------------------------
        | Primary variant
        |--------------------------------------------------------------------------
        |
        | SEO is product-level in Shopify.
        | Therefore use the Fullscript primary variant's SKU and price.
        |
        */

        $primaryVariant = $this->getPrimaryVariant($variants);

        $primarySku = $primaryVariant['sku'] ?? '';
        $primaryPrice = $primaryVariant['price'] ?? '0.00';

        return [

            'fullscript_product_id' => $productId,

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            'title' => $name,

            'description_html' => $product['description_html'] ?? '',

            'vendor' => $vendor,

            'product_type' => 'Supplement',

            /*
             * Keep the original Fullscript product status.
             */
            'fullscript_status' => $product['status'] ?? null,

            /*
             * Shopify product status.
             *
             * Available = ACTIVE
             * Anything else = ARCHIVED
             */
            'status' => ($product['status'] ?? '') === 'available'
                ? 'ACTIVE'
                : 'ARCHIVED',

            'handle' => Str::slug(
                $vendor . '-' . $name . '-' . $productId
            ),

            /*
            |--------------------------------------------------------------------------
            | Gift card
            |--------------------------------------------------------------------------
            */

            'gift_card' => false,

            /*
            |--------------------------------------------------------------------------
            | SEO
            |--------------------------------------------------------------------------
            |
            | SEO title:
            | Product Name | SKU | Vendor
            |
            | SEO description:
            | Shop the best professional Vitamins and Supplements!
            | Discover our Product (SKU) for Price – all at discounted
            | prices from the most trusted brands.
            |
            */

            'seo' => [
                'title' => $this->buildSeoTitle(
                    $name,
                    $primarySku,
                    $vendor
                ),

                'description' => $this->buildSeoDescription(
                    $name,
                    $primarySku,
                    $primaryPrice
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Product options
            |--------------------------------------------------------------------------
            */

            'product_options' => [
                [
                    'name' => 'Size',
                    'position' => 1,
                    'values' => $this->transformOptionValues($variants),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Variants
            |--------------------------------------------------------------------------
            */

            'variants' => $variants,

            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
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
            |--------------------------------------------------------------------------
            | Package option
            |--------------------------------------------------------------------------
            */

            $optionValue = $this->buildOptionValue($variant);

            if (isset($seenOptionValues[$optionValue])) {
                throw new RuntimeException(
                    "Duplicate Size option value '{$optionValue}' found in Fullscript product."
                );
            }

            $seenOptionValues[$optionValue] = true;

            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            */

            $availability = strtolower(
                trim(
                    (string) ($variant['availability'] ?? '')
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Fixed Shopify inventory
            |--------------------------------------------------------------------------
            |
            | Fullscript does NOT provide an exact inventory count.
            |
            | In Stock     -> 88
            | Everything else -> 0
            |
            */

            $quantity = $availability === 'in stock'
                ? (int) config('fullscript.default_inventory', 88)
                : 0;

            /*
            |--------------------------------------------------------------------------
            | Optional cost / weight data
            |--------------------------------------------------------------------------
            |
            | These fields are intentionally nullable until we confirm
            | the exact Fullscript response field names.
            |
            */

            $cost = $variant['cost'] ?? null;

            $weight = $variant['weight'] ?? null;

            $weightUnit = $variant['weight_unit']
                ?? $variant['weightUnit']
                ?? null;

            $result[] = [

                'fullscript_variant_id' => $variantId,

                'sku' => $sku,

                /*
                |--------------------------------------------------------------------------
                | Price
                |--------------------------------------------------------------------------
                */

                'price' => (string) (
                    $variant['msrp'] ?? '0.00'
                ),

                /*
                |--------------------------------------------------------------------------
                | Barcode
                |--------------------------------------------------------------------------
                */

                'barcode' => !empty($variant['upc'])
                    ? (string) $variant['upc']
                    : null,

                /*
                |--------------------------------------------------------------------------
                | Cost
                |--------------------------------------------------------------------------
                */

                'cost' => $cost !== null
                    ? (string) $cost
                    : null,

                /*
                |--------------------------------------------------------------------------
                | Weight
                |--------------------------------------------------------------------------
                */

                'weight' => $weight !== null
                    ? (float) $weight
                    : null,

                'weight_unit' => $weightUnit,

                /*
                |--------------------------------------------------------------------------
                | Package information
                |--------------------------------------------------------------------------
                */

                'units' => $variant['units'] ?? null,

                'unit_of_measure' => $variant['unit_of_measure'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | Fullscript status
                |--------------------------------------------------------------------------
                */

                'availability' => $variant['availability'] ?? null,

                'status' => $variant['status'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | Keep explicit Fullscript status
                |--------------------------------------------------------------------------
                */

                'fullscript_status' => $variant['status'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | Other Fullscript data
                |--------------------------------------------------------------------------
                */

                'supplier_sku' => $variant['supplier_sku'] ?? null,

                'primary' => (bool) (
                    $variant['primary'] ?? false
                ),

                /*
                |--------------------------------------------------------------------------
                | Shopify inventory
                |--------------------------------------------------------------------------
                */

                'quantity' => $quantity,

                /*
                |--------------------------------------------------------------------------
                | Shopify variant settings
                |--------------------------------------------------------------------------
                */

                'inventory_tracker' => 'shopify',

                'requires_shipping' => true,

                'taxable' => true,

                'inventory_policy' => 'DENY',

                /*
                |--------------------------------------------------------------------------
                | Shopify option
                |--------------------------------------------------------------------------
                */

                'option_name' => 'Size',

                'option_value' => $optionValue,
            ];
        }

        return $result;
    }

    /**
     * Find the Fullscript primary variant.
     */
    protected function getPrimaryVariant(array $variants): array
    {
        foreach ($variants as $variant) {
            if (!empty($variant['primary'])) {
                return $variant;
            }
        }

        /*
         * Fallback to first variant.
         */
        return $variants[0] ?? [];
    }

    /**
     * Build SEO title.
     */
    protected function buildSeoTitle(
        string $productName,
        string $sku,
        string $vendor
    ): string {
        return trim(
            $productName . ' | ' . $sku . ' | ' . $vendor
        );
    }

    /**
     * Build SEO description.
     */
    protected function buildSeoDescription(
        string $productName,
        string $sku,
        string $price
    ): string {
        return sprintf(
            'Shop the best professional Vitamins and Supplements! Discover our %s (%s) for %s – all at discounted prices from the most trusted brands.',
            $productName,
            $sku,
            $this->formatPrice($price)
        );
    }

    /**
     * Format price for SEO.
     */
    protected function formatPrice(string $price): string
    {
        if ($price === '') {
            return '$0.00';
        }

        /*
         * Fullscript price may already contain a currency symbol.
         */
        if (preg_match('/[$£€]/', $price)) {
            return $price;
        }

        return '$' . number_format(
            (float) $price,
            2,
            '.',
            ''
        );
    }

    /**
     * Build Shopify Size option value.
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

                'alt' => $product['name']
                    ?? 'Fullscript product',
            ],
        ];
    }
}