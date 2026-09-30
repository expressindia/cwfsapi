<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use RuntimeException;

class ShopifyProductService
{
    public function __construct(
        protected ShopifyGraphQLService $graphql
    ) {
    }

    /**
     * Get a Shopify product by ID.
     */
    public function getProduct(
        string $productId
    ): ?array {

        $query = <<<'GRAPHQL'
query GetProduct($id: ID!) {
    product(id: $id) {
        id
        title
        handle
        status
        vendor
        productType
        descriptionHtml
        isGiftCard
        tags

        seo {
            title
            description
        }

        variants(first: 250) {
            nodes {
                id
                title
                sku
                barcode
                price
                compareAtPrice
                taxable
                inventoryPolicy

                inventoryItem {
                    id
                    sku
                    tracked
                    requiresShipping

                    unitCost {
                        amount
                        currencyCode
                    }

                    inventoryLevels(
                        first: 50
                        includeInactive: true
                    ) {
                        nodes {
                            id
                            isActive

                            location {
                                id
                                name
                                isActive
                            }

                            quantities(
                                names: ["available"]
                            ) {
                                name
                                quantity
                            }
                        }
                    }
                }
            }
        }
    }
}
GRAPHQL;

        $data =
            $this->graphql->execute(
                $query,
                [
                    'id' =>
                        $productId,
                ]
            );

        return $data['product']
            ?? null;
    }

    /**
     * Find Shopify product variant by SKU.
     */
    public function findProductBySku(
        string $sku
    ): ?array {

        $query = <<<'GRAPHQL'
query SearchVariants($query: String!) {
    productVariants(
        first: 10
        query: $query
    ) {
        nodes {
            id
            sku

            product {
                id
                title
                handle
                status
            }

            inventoryItem {
                id
                sku
            }
        }
    }
}
GRAPHQL;

        $data =
            $this->graphql->execute(
                $query,
                [
                    'query' =>
                        'sku:"'
                        . addslashes($sku)
                        . '"',
                ]
            );

        $variants =
            $data[
                'productVariants'
            ]['nodes']
            ?? [];

        foreach ($variants as $variant) {

            if (
                trim(
                    (string) (
                        $variant['sku']
                        ?? ''
                    )
                )
                === $sku
            ) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Create or update Shopify product.
     */
    public function createOrUpdate(
        array $product,
        ?string $shopifyProductId = null
    ): array {

        $fsWarehouseLocationId =
            $this->getFsWarehouseLocationId();

        $input = [
            'title' =>
                $product['title'],

            'descriptionHtml' =>
                $product['description_html']
                ?? '',

            'vendor' =>
                $product['vendor']
                ?? 'Fullscript',

            'productType' =>
                $product['product_type']
                ?? 'Vitamins & Supplements',

            'status' =>
                $product['status']
                ?? 'ACTIVE',

            'giftCard' =>
                (bool) (
                    $product['gift_card']
                    ?? false
                ),

            'tags' =>
                $product['tags']
                ?? [],

            'productOptions' =>
                $product['product_options']
                ?? [],
        ];

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        |
        | Intentionally NOT included.
        |
        | Shopify Category will remain blank.
        |
        */

        if (!empty($product['seo'])) {

            $input['seo'] = [
                'title' =>
                    $product['seo']['title']
                    ?? null,

                'description' =>
                    $product['seo']['description']
                    ?? null,
            ];
        }

        if (!empty($product['handle'])) {

            $input['handle'] =
                $product['handle'];
        }

        if (!empty($product['images'])) {

            $input['files'] = [];

            foreach (
                $product['images']
                as $image
            ) {

                if (empty($image['url'])) {
                    continue;
                }

                $input['files'][] = [
                    'originalSource' =>
                        $image['url'],

                    'alt' =>
                        $image['alt']
                        ?? $product['title'],

                    'contentType' =>
                        'IMAGE',
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Shopify product
        |--------------------------------------------------------------------------
        */

        $existingProduct = null;

        if ($shopifyProductId) {

            $existingProduct =
                $this->getProduct(
                    $shopifyProductId
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Existing variants indexed by SKU
        |--------------------------------------------------------------------------
        */

        $existingVariantsBySku = [];

        if ($existingProduct) {

            foreach (
                $existingProduct[
                    'variants'
                ]['nodes'] ?? []
                as $existingVariant
            ) {

                $existingSku =
                    trim(
                        (string) (
                            $existingVariant[
                                'sku'
                            ] ?? ''
                        )
                    );

                if ($existingSku === '') {
                    continue;
                }

                $existingVariantsBySku[
                    $existingSku
                ] =
                    $existingVariant;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Activate FSWarehouse for existing variants
        |--------------------------------------------------------------------------
        */

        if ($existingProduct) {

            foreach (
                $product['variants']
                ?? []
                as $variant
            ) {

                $sku =
                    trim(
                        (string) (
                            $variant['sku']
                            ?? ''
                        )
                    );

                if (
                    $sku === ''
                    ||
                    !isset(
                        $existingVariantsBySku[
                            $sku
                        ]
                    )
                ) {
                    continue;
                }

                $existingVariant =
                    $existingVariantsBySku[
                        $sku
                    ];

                $inventoryItemId =
                    $existingVariant[
                        'inventoryItem'
                    ]['id']
                    ?? null;

                if (!$inventoryItemId) {
                    continue;
                }

                $this->activateInventoryLocation(
                    $inventoryItemId,
                    $fsWarehouseLocationId
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Build variants
        |--------------------------------------------------------------------------
        */

        if (!empty($product['variants'])) {

            $input['variants'] = [];

            foreach (
                $product['variants']
                as $variant
            ) {

                $sku =
                    trim(
                        (string) (
                            $variant['sku']
                            ?? ''
                        )
                    );

                $variantInput = [
                    'sku' =>
                        $sku,

                    'price' =>
                        (string) (
                            $variant['price']
                            ?? '0.00'
                        ),

                    'inventoryPolicy' =>
                        'DENY',

                    'taxable' =>
                        true,

                    'inventoryItem' => [
                        'tracked' =>
                            true,

                        'requiresShipping' =>
                            true,
                    ],

                    'optionValues' => [
                        [
                            'optionName' =>
                                $variant[
                                    'option_name'
                                ]
                                ?? 'Size',

                            'name' =>
                                $variant[
                                    'option_value'
                                ]
                                ?? 'Default',
                        ],
                    ],
                ];

                /*
                |--------------------------------------------------------------------------
                | Existing variant
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $existingVariantsBySku[
                            $sku
                        ]
                    )
                ) {

                    $variantInput['id'] =
                        $existingVariantsBySku[
                            $sku
                        ]['id'];
                }

                /*
                |--------------------------------------------------------------------------
                | Barcode
                |--------------------------------------------------------------------------
                */

                if (
                    !empty(
                        $variant['barcode']
                    )
                ) {

                    $variantInput['barcode'] =
                        (string) (
                            $variant['barcode']
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Compare-at price
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $variant[
                            'compare_at_price'
                        ]
                    )
                    &&
                    $variant[
                        'compare_at_price'
                    ] !== null
                ) {

                    $variantInput[
                        'compareAtPrice'
                    ] =
                        $variant[
                            'compare_at_price'
                        ];
                }

                /*
                |--------------------------------------------------------------------------
                | Cost
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $variant['cost']
                    )
                    &&
                    $variant['cost'] !== null
                    &&
                    $variant['cost'] !== ''
                ) {

                    $variantInput[
                        'inventoryItem'
                    ]['cost'] =
                        (string) (
                            $variant['cost']
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Weight intentionally omitted
                |--------------------------------------------------------------------------
                |
                | Do NOT add:
                |
                | measurement
                | weight
                | weightUnit
                |
                */

                /*
                |--------------------------------------------------------------------------
                | Inventory
                |--------------------------------------------------------------------------
                */

                $variantInput[
                    'inventoryQuantities'
                ] = [
                    [
                        'locationId' =>
                            $fsWarehouseLocationId,

                        'name' =>
                            'available',

                        'quantity' =>
                            (int) (
                                $variant['quantity']
                                ?? 0
                            ),
                    ],
                ];

                $input['variants'][] =
                    $variantInput;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ProductSet mutation
        |--------------------------------------------------------------------------
        */

        $mutation = <<<'GRAPHQL'
mutation ProductSet(
    $input: ProductSetInput!,
    $identifier: ProductSetIdentifiers
) {
    productSet(
        input: $input,
        identifier: $identifier,
        synchronous: true
    ) {
        product {
            id
            title
            handle
            status
            vendor
            productType
            descriptionHtml
            isGiftCard
            tags

            seo {
                title
                description
            }

            media(first: 10) {
                nodes {
                    id
                    alt
                    mediaContentType
                    status
                }
            }

            variants(first: 250) {
                nodes {
                    id
                    title
                    sku
                    barcode
                    price
                    compareAtPrice
                    taxable
                    inventoryPolicy

                    inventoryItem {
                        id
                        sku
                        tracked
                        requiresShipping

                        unitCost {
                            amount
                            currencyCode
                        }
                    }
                }
            }
        }

        userErrors {
            field
            message
            code
        }
    }
}
GRAPHQL;

        $identifier = null;

        if ($shopifyProductId) {

            $identifier = [
                'id' =>
                    $shopifyProductId,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Shopify
        |--------------------------------------------------------------------------
        */

        $data =
            $this->graphql->execute(
                $mutation,
                [
                    'input' =>
                        $input,

                    'identifier' =>
                        $identifier,
                ]
            );

        $result =
            $data['productSet']
            ?? null;

        if (!$result) {

            throw new RuntimeException(
                'Shopify productSet returned no result.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shopify user errors
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $result['userErrors']
            )
        ) {

            throw new RuntimeException(
                json_encode(
                    $result['userErrors'],
                    JSON_PRETTY_PRINT
                )
            );
        }

        if (
            empty(
                $result['product']
            )
        ) {

            throw new RuntimeException(
                'Shopify productSet did not return product.'
            );
        }

        $shopifyProduct =
            $result['product'];

        /*
        |--------------------------------------------------------------------------
        | Make FSWarehouse the only active inventory location.
        |--------------------------------------------------------------------------
        */

        $this->makeFsWarehouseOnly(
            $shopifyProduct,
            $fsWarehouseLocationId
        );

        return $shopifyProduct;
    }

    /**
     * Get FSWarehouse location ID dynamically.
     */
    protected function getFsWarehouseLocationId(): string
    {
        $shopifyToken =
            ShopifyToken::query()->first();

        if (!$shopifyToken) {

            throw new RuntimeException(
                'Shopify token was not found.'
            );
        }

        $locationId =
            $shopifyToken->fulfillment_location_id;

        if (!$locationId) {

            throw new RuntimeException(
                'Shopify FSWarehouse location ID is not configured.'
            );
        }

        $query = <<<'GRAPHQL'
query GetLocation($id: ID!) {
    location(id: $id) {
        id
        name
        isActive
    }
}
GRAPHQL;

        $data =
            $this->graphql->execute(
                $query,
                [
                    'id' =>
                        $locationId,
                ]
            );

        $location =
            $data['location']
            ?? null;

        if (!$location) {

            throw new RuntimeException(
                "Shopify location {$locationId} was not found."
            );
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $location['name']
                        ?? ''
                    )
                )
            ) !== 'fswarehouse'
        ) {

            throw new RuntimeException(
                "Configured fulfillment location is '{$location['name']}', not FSWarehouse."
            );
        }

        return $location['id'];
    }

    /**
     * Activate inventory at FSWarehouse.
     */
    protected function activateInventoryLocation(
        string $inventoryItemId,
        string $locationId
    ): void {

        $mutation = <<<'GRAPHQL'
mutation ActivateInventoryLocation(
    $inventoryItemId: ID!,
    $inventoryItemUpdates: [InventoryBulkToggleActivationInput!]!
) {
    inventoryBulkToggleActivation(
        inventoryItemId: $inventoryItemId,
        inventoryItemUpdates: $inventoryItemUpdates
    ) {
        inventoryItem {
            id
        }

        userErrors {
            field
            message
            code
        }
    }
}
GRAPHQL;

        $data =
            $this->graphql->execute(
                $mutation,
                [
                    'inventoryItemId' =>
                        $inventoryItemId,

                    'inventoryItemUpdates' => [
                        [
                            'locationId' =>
                                $locationId,

                            'activate' =>
                                true,
                        ],
                    ],
                ]
            );

        $errors =
            $data[
                'inventoryBulkToggleActivation'
            ]['userErrors']
            ?? [];

        if (!empty($errors)) {

            throw new RuntimeException(
                'Unable to activate FSWarehouse: '
                .
                json_encode(
                    $errors,
                    JSON_PRETTY_PRINT
                )
            );
        }
    }

    /**
     * Make FSWarehouse the only active inventory location.
     */
    protected function makeFsWarehouseOnly(
        array $shopifyProduct,
        string $fsWarehouseLocationId
    ): void {

        foreach (
            $shopifyProduct[
                'variants'
            ]['nodes'] ?? []
            as $variant
        ) {

            $inventoryItemId =
                $variant[
                    'inventoryItem'
                ]['id']
                ?? null;

            if (!$inventoryItemId) {
                continue;
            }

            $this->deactivateOtherLocations(
                $inventoryItemId,
                $fsWarehouseLocationId
            );
        }
    }

    /**
     * Deactivate all active locations except FSWarehouse.
     */
    protected function deactivateOtherLocations(
        string $inventoryItemId,
        string $fsWarehouseLocationId
    ): void {

        $query = <<<'GRAPHQL'
query GetInventoryLevels($id: ID!) {
    inventoryItem(id: $id) {
        id

        inventoryLevels(
            first: 50
            includeInactive: false
        ) {
            nodes {
                location {
                    id
                    name
                }

                isActive
            }
        }
    }
}
GRAPHQL;

        $data =
            $this->graphql->execute(
                $query,
                [
                    'id' =>
                        $inventoryItemId,
                ]
            );

        $levels =
            $data[
                'inventoryItem'
            ]['inventoryLevels']['nodes']
            ?? [];

        foreach ($levels as $level) {

            $locationId =
                $level[
                    'location'
                ]['id']
                ?? null;

            if (!$locationId) {
                continue;
            }

            if (
                $locationId ===
                $fsWarehouseLocationId
            ) {
                continue;
            }

            if (
                !(
                    $level['isActive']
                    ?? false
                )
            ) {
                continue;
            }

            $this->deactivateInventoryLocation(
                $inventoryItemId,
                $locationId
            );
        }
    }

    /**
     * Deactivate inventory at a location.
     */
    protected function deactivateInventoryLocation(
        string $inventoryItemId,
        string $locationId
    ): void {

        $mutation = <<<'GRAPHQL'
mutation DeactivateInventoryLocation(
    $inventoryItemId: ID!,
    $inventoryItemUpdates: [InventoryBulkToggleActivationInput!]!
) {
    inventoryBulkToggleActivation(
        inventoryItemId: $inventoryItemId,
        inventoryItemUpdates: $inventoryItemUpdates
    ) {
        inventoryItem {
            id
        }

        userErrors {
            field
            message
            code
        }
    }
}
GRAPHQL;

        $data =
            $this->graphql->execute(
                $mutation,
                [
                    'inventoryItemId' =>
                        $inventoryItemId,

                    'inventoryItemUpdates' => [
                        [
                            'locationId' =>
                                $locationId,

                            'activate' =>
                                false,
                        ],
                    ],
                ]
            );

        $errors =
            $data[
                'inventoryBulkToggleActivation'
            ]['userErrors']
            ?? [];

        if (!empty($errors)) {

            throw new RuntimeException(
                'Unable to deactivate inventory location: '
                .
                json_encode(
                    $errors,
                    JSON_PRETTY_PRINT
                )
            );
        }
    }
}