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

        $variants = $this->transformVariants(
            $product['variants'] ?? []
        );

        $primaryVariant = $this->getPrimaryVariant(
            $variants
        );

        $fullscriptStatus = strtolower(
            trim(
                (string) (
                    $product['status'] ?? ''
                )
            )
        );

        $shopifyStatus =
            $fullscriptStatus === 'available'
                ? 'ACTIVE'
                : 'ARCHIVED';

        return [
            'fullscript_product_id' =>
                $productId,

            'fullscript_status' =>
                $fullscriptStatus,

            'title' =>
                $name,

            'description_html' =>
                $product['description_html'] ?? '',

            'vendor' =>
                $product['brand']['name']
                ?? 'Fullscript',

            'product_type' =>
                'Vitamins & Supplements',

            'status' =>
                $shopifyStatus,

            'gift_card' =>
                false,

            'handle' =>
                $this->generateHandle($product),

            'tags' =>
                $this->transformTags($product),

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

            'seo' =>
                $this->transformSeo(
                    $product,
                    $primaryVariant
                ),

            'variants' =>
                $variants,

            'images' =>
                $this->transformImages($product),
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

        $defaultInventory = (int) config(
            'fullscript.default_inventory',
            88
        );

        foreach ($variants as $variant) {

            $variantId =
                $variant['id'] ?? null;

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

            if (isset($seenSkus[$sku])) {
                throw new RuntimeException(
                    "Duplicate SKU {$sku} found in Fullscript product."
                );
            }

            $seenSkus[$sku] = true;

            $optionValue =
                $this->buildOptionValue($variant);

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

            $availability = strtolower(
                trim(
                    (string) (
                        $variant['availability']
                        ?? ''
                    )
                )
            );

            $quantity =
                $availability === 'in stock'
                    ? $defaultInventory
                    : 0;

            $result[] = [
                'fullscript_variant_id' =>
                    $variantId,

                'sku' =>
                    $sku,

                'price' =>
                    (string) (
                        $variant['msrp']
                        ?? '0.00'
                    ),

                'barcode' =>
                    !empty($variant['upc'])
                        ? (string) $variant['upc']
                        : null,

                'cost' =>
                    isset($variant['cost'])
                    && $variant['cost'] !== ''
                        ? (string) $variant['cost']
                        : null,

                'units' =>
                    $variant['units'] ?? null,

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

                'option_name' =>
                    'Size',

                'option_value' =>
                    $optionValue,

                'weight' =>
                    null,

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

            if (!empty($variant['primary'])) {
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
     */
    protected function transformTags(
        array $product
    ): array {

        $tags = [];

        $productName = trim(
            (string) (
                $product['name'] ?? ''
            )
        );

        if ($productName !== '') {
            $tags[] = $productName;
        }

        $brandName = trim(
            (string) (
                $product['brand']['name']
                ?? ''
            )
        );

        if ($brandName !== '') {
            $tags[] = $brandName;
        }

        $tags[] =
            'Vitamins & Supplements';

        return array_values(
            array_unique(
                array_filter($tags)
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
            $variant['units'] ?? null;

        $unitOfMeasure =
            trim(
                (string) (
                    $variant['unit_of_measure']
                    ?? ''
                )
            );

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

        if (
            $units !== null
            &&
            $units !== ''
            &&
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

            if (isset($seen[$value])) {
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
     * Transform SEO.
     */
    protected function transformSeo(
        array $product,
        array $primaryVariant
    ): array {

        $productName =
            $product['name'] ?? '';

        $sku =
            $primaryVariant['sku'] ?? '';

        $vendor =
            $product['brand']['name']
            ?? 'Fullscript';

        $price =
            $primaryVariant['price']
            ?? '0.00';

        $title =
            $productName
            . ' | '
            . $sku
            . ' | '
            . $vendor;

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