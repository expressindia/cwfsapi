<?php

namespace App\Services\ProductSync;

use Illuminate\Support\Str;
use RuntimeException;

class ProductTransformer
{
    /**
     * Transform Fullscript product into Shopify sync structure.
     */
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

        /*
        |--------------------------------------------------------------------------
        | Transform variants
        |--------------------------------------------------------------------------
        */

        $variants = $this->transformVariants(
            $product['variants'] ?? []
        );

        /*
        |--------------------------------------------------------------------------
        | Primary variant
        |--------------------------------------------------------------------------
        |
        | Shopify SEO is product-level.
        | Therefore SKU and price are taken from the primary variant.
        |
        */

        $primaryVariant = $this->getPrimaryVariant(
            $variants
        );

        /*
        |--------------------------------------------------------------------------
        | Fullscript product status
        |--------------------------------------------------------------------------
        */

        $fullscriptStatus = strtolower(
            trim(
                (string) (
                    $product['status'] ?? ''
                )
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Shopify product status
        |--------------------------------------------------------------------------
        */

        $shopifyStatus =
            $fullscriptStatus === 'available'
                ? 'ACTIVE'
                : 'ARCHIVED';

        /*
        |--------------------------------------------------------------------------
        | Product
        |--------------------------------------------------------------------------
        */

        return [
            /*
            |--------------------------------------------------------------------------
            | Fullscript identifiers
            |--------------------------------------------------------------------------
            */

            'fullscript_product_id' =>
                $productId,

            'fullscript_status' =>
                $fullscriptStatus,

            /*
            |--------------------------------------------------------------------------
            | Shopify product
            |--------------------------------------------------------------------------
            */

            'title' =>
                $name,

            'description_html' =>
                $product['description_html'] ?? '',

            'vendor' =>
                $product['brand']['name']
                ?? 'Fullscript',

            /*
            |--------------------------------------------------------------------------
            | Product Type
            |--------------------------------------------------------------------------
            |
            | Shopify Product Type:
            | Vitamins & Supplements
            |
            */

            'product_type' =>
                'Vitamins & Supplements',

            /*
            |--------------------------------------------------------------------------
            | Product Status
            |--------------------------------------------------------------------------
            */

            'status' =>
                $shopifyStatus,

            /*
            |--------------------------------------------------------------------------
            | Gift Card
            |--------------------------------------------------------------------------
            */

            'gift_card' =>
                false,

            /*
            |--------------------------------------------------------------------------
            | Handle
            |--------------------------------------------------------------------------
            */

            'handle' =>
                $this->generateHandle(
                    $product
                ),

            /*
            |--------------------------------------------------------------------------
            | Product Tags
            |--------------------------------------------------------------------------
            |
            | Exactly:
            |
            | 1. Product name
            | 2. Brand name
            | 3. Vitamins & Supplements
            |
            */

            'tags' =>
                $this->transformTags(
                    $product
                ),

            /*
            |--------------------------------------------------------------------------
            | Product Options
            |--------------------------------------------------------------------------
            */

            'product_options' => [
                [
                    'name' =>
                        'Size',

                    'position' =>
                        1,

                    'values' =>
                        $this->transformOptionValues(
                            $variants
                        ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | SEO
            |--------------------------------------------------------------------------
            */

            'seo' =>
                $this->transformSeo(
                    $product,
                    $primaryVariant
                ),

            /*
            |--------------------------------------------------------------------------
            | Variants
            |--------------------------------------------------------------------------
            */

            'variants' =>
                $variants,

            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
            */

            'images' =>
                $this->transformImages(
                    $product
                ),
        ];
    }

    /**
     * Transform Fullscript variants.
     */
    protected function transformVariants(
        array $variants
    ): array {

        if (empty($variants)) {
            throw new RuntimeException(
                'Fullscript product has no variants.'
            );
        }

        $result = [];

        $seenSkus = [];

        $seenOptionValues = [];

        /*
        |--------------------------------------------------------------------------
        | Fixed Shopify inventory
        |--------------------------------------------------------------------------
        |
        | Config:
        |
        | FULLSCRIPT_DEFAULT_INVENTORY=88
        |
        */

        $defaultInventory = (int) config(
            'fullscript.default_inventory',
            88
        );

        foreach ($variants as $variant) {

            /*
            |--------------------------------------------------------------------------
            | Fullscript Variant ID
            |--------------------------------------------------------------------------
            */

            $variantId =
                $variant['id'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | SKU
            |--------------------------------------------------------------------------
            */

            $sku = trim(
                (string) (
                    $variant['sku'] ?? ''
                )
            );

            if ($sku === '') {
                throw new RuntimeException(
                    'Fullscript variant is missing SKU.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Duplicate SKU protection
            |--------------------------------------------------------------------------
            */

            if (isset($seenSkus[$sku])) {
                throw new RuntimeException(
                    "Duplicate SKU {$sku} found in Fullscript product."
                );
            }

            $seenSkus[$sku] = true;

            /*
            |--------------------------------------------------------------------------
            | Option value
            |--------------------------------------------------------------------------
            */

            $optionValue =
                $this->buildOptionValue(
                    $variant
                );

            if (
                isset(
                    $seenOptionValues[
                        $optionValue
                    ]
                )
            ) {
                throw new RuntimeException(
                    "Duplicate Size option value '{$optionValue}' found in Fullscript product."
                );
            }

            $seenOptionValues[
                $optionValue
            ] = true;

            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            */

            $availability = strtolower(
                trim(
                    (string) (
                        $variant['availability']
                        ?? ''
                    )
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Inventory Quantity
            |--------------------------------------------------------------------------
            |
            | Fullscript does not provide an exact inventory count.
            |
            | In Stock      = 88
            | Backordered   = 0
            | Unavailable   = 0
            | Discontinued  = 0
            |
            */

            $quantity =
                $availability === 'in stock'
                    ? $defaultInventory
                    : 0;

            /*
            |--------------------------------------------------------------------------
            | Variant
            |--------------------------------------------------------------------------
            */

            $result[] = [

                /*
                |--------------------------------------------------------------------------
                | Fullscript IDs
                |--------------------------------------------------------------------------
                */

                'fullscript_variant_id' =>
                    $variantId,

                'sku' =>
                    $sku,

                /*
                |--------------------------------------------------------------------------
                | Price
                |--------------------------------------------------------------------------
                */

                'price' =>
                    (string) (
                        $variant['msrp']
                        ?? '0.00'
                    ),

                /*
                |--------------------------------------------------------------------------
                | Barcode
                |--------------------------------------------------------------------------
                |
                | Fullscript UPC -> Shopify Barcode
                |
                */

                'barcode' =>
                    !empty(
                        $variant['upc']
                    )
                        ? (string) (
                            $variant['upc']
                        )
                        : null,

                /*
                |--------------------------------------------------------------------------
                | Cost
                |--------------------------------------------------------------------------
                */

                'cost' =>
                    isset(
                        $variant['cost']
                    )
                    &&
                    $variant['cost'] !== ''
                        ? (string) (
                            $variant['cost']
                        )
                        : null,

                /*
                |--------------------------------------------------------------------------
                | Fullscript variant information
                |--------------------------------------------------------------------------
                */

                'units' =>
                    $variant['units']
                    ?? null,

                'unit_of_measure' =>
                    $variant['unit_of_measure']
                    ?? null,

                'availability' =>
                    $variant['availability']
                    ?? null,

                'status' =>
                    $variant['status']
                    ?? null,

                'fullscript_status' =>
                    strtolower(
                        trim(
                            (string) (
                                $variant['status']
                                ?? ''
                            )
                        )
                    ),

                'supplier_sku' =>
                    $variant['supplier_sku']
                    ?? null,

                'primary' =>
                    (bool) (
                        $variant['primary']
                        ?? false
                    ),

                /*
                |--------------------------------------------------------------------------
                | Shopify Inventory
                |--------------------------------------------------------------------------
                */

                'quantity' =>
                    $quantity,

                'inventory_tracker' =>
                    'shopify',

                'requires_shipping' =>
                    true,

                'taxable' =>
                    true,

                'inventory_policy' =>
                    'DENY',

                /*
                |--------------------------------------------------------------------------
                | Shopify Option
                |--------------------------------------------------------------------------
                */

                'option_name' =>
                    'Size',

                'option_value' =>
                    $optionValue,

                /*
                |--------------------------------------------------------------------------
                | Weight
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                |
                | Weight is intentionally not imported.
                |
                */

                'weight' =>
                    null,

                /*
                |--------------------------------------------------------------------------
                | Variant Weight Unit
                |--------------------------------------------------------------------------
                |
                | Keep blank.
                |
                */

                'variant_weight_unit' =>
                    null,
            ];
        }

        return $result;
    }

    /**
     * Get primary variant.
     */
    protected function getPrimaryVariant(
        array $variants
    ): array {

        foreach ($variants as $variant) {

            if (
                !empty(
                    $variant['primary']
                )
            ) {
                return $variant;
            }
        }

        return $variants[0] ?? [];
    }

    /**
     * Generate Shopify product handle.
     */
    protected function generateHandle(
        array $product
    ): string {

        $brand =
            $product['brand']['name']
            ?? 'Fullscript';

        $name =
            $product['name']
            ?? '';

        $productId =
            $product['id']
            ?? '';

        return Str::slug(
            $brand
            . '-'
            . $name
            . '-'
            . $productId
        );
    }

    /**
     * Generate Shopify product tags.
     *
     * Tags:
     *
     * 1. Product name
     * 2. Brand name
     * 3. Vitamins & Supplements
     */
    protected function transformTags(
        array $product
    ): array {

        $tags = [];

        /*
        |--------------------------------------------------------------------------
        | Product Name
        |--------------------------------------------------------------------------
        */

        $productName = trim(
            (string) (
                $product['name'] ?? ''
            )
        );

        if ($productName !== '') {
            $tags[] = $productName;
        }

        /*
        |--------------------------------------------------------------------------
        | Brand Name
        |--------------------------------------------------------------------------
        */

        $brandName = trim(
            (string) (
                $product['brand']['name']
                ?? ''
            )
        );

        if ($brandName !== '') {
            $tags[] = $brandName;
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed Tag
        |--------------------------------------------------------------------------
        */

        $tags[] =
            'Vitamins & Supplements';

        /*
        |--------------------------------------------------------------------------
        | Remove duplicates
        |--------------------------------------------------------------------------
        */

        return array_values(
            array_unique(
                array_filter(
                    $tags
                )
            )
        );
    }

    /**
     * Build variant option value.
     */
    protected function buildOptionValue(
        array $variant
    ): string {

        $units =
            $variant['units']
            ?? null;

        $unitOfMeasure =
            trim(
                (string) (
                    $variant[
                        'unit_of_measure'
                    ]
                    ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Units + Unit of Measure
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | 60 + capsules
        | = 60 capsules
        |
        */

        if (
            $units !== null
            &&
            $units !== ''
            &&
            (int) $units > 0
            &&
            $unitOfMeasure !== ''
        ) {

            return trim(
                (string) $units
                . ' '
                . $unitOfMeasure
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Units only
        |--------------------------------------------------------------------------
        */

        if (
            $units !== null
            &&
            $units !== ''
            &&
            (int) $units > 0
        ) {

            return (string) $units;
        }

        /*
        |--------------------------------------------------------------------------
        | Unit of Measure only
        |--------------------------------------------------------------------------
        */

        if ($unitOfMeasure !== '') {
            return $unitOfMeasure;
        }

        return 'Default';
    }

    /**
     * Transform Shopify product options.
     */
    protected function transformOptionValues(
        array $variants
    ): array {

        $values = [];

        $seen = [];

        foreach ($variants as $variant) {

            $value =
                $variant['option_value']
                ?? null;

            if (!$value) {
                continue;
            }

            if (
                isset(
                    $seen[$value]
                )
            ) {
                continue;
            }

            $seen[$value] = true;

            $values[] = [
                'name' =>
                    $value,
            ];
        }

        if (empty($values)) {

            $values[] = [
                'name' =>
                    'Default',
            ];
        }

        return $values;
    }

    /**
     * Transform SEO fields.
     *
     * SEO Title:
     *
     * {{Product Name}} | {{SKU}} | {{Vendor Name}}
     *
     * SEO Description:
     *
     * Shop the best professional Vitamins and Supplements!
     * Discover our {{Product Title}} ({{SKU}}) for {{Price}}
     * – all at discounted prices from the most trusted brands.
     */
    protected function transformSeo(
        array $product,
        array $primaryVariant
    ): array {

        $productName =
            $product['name']
            ?? '';

        $sku =
            $primaryVariant['sku']
            ?? '';

        $vendor =
            $product['brand']['name']
            ?? 'Fullscript';

        $price =
            $primaryVariant['price']
            ?? '0.00';

        /*
        |--------------------------------------------------------------------------
        | SEO Title
        |--------------------------------------------------------------------------
        */

        $title =
            $productName
            . ' | '
            . $sku
            . ' | '
            . $vendor;

        /*
        |--------------------------------------------------------------------------
        | SEO Description
        |--------------------------------------------------------------------------
        */

        $description =
            'Shop the best professional Vitamins and Supplements! '
            . 'Discover our '
            . $productName
            . ' ('
            . $sku
            . ') for '
            . $price
            . ' – all at discounted prices from the most trusted brands.';

        return [
            'title' =>
                $title,

            'description' =>
                $description,
        ];
    }

    /**
     * Transform product image.
     */
    protected function transformImages(
        array $product
    ): array {

        $imageUrl =
            $product['image_url_large']
            ?? null;

        if (!$imageUrl) {
            return [];
        }

        return [
            [
                'url' =>
                    $imageUrl,

                'alt' =>
                    $product['name']
                    ?? 'Fullscript product',
            ],
        ];
    }
}