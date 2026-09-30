<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VendorInventoryMoveService
{
    /**
     * Number of products/variants/inventory items to process
     * in each GraphQL batch.
     *
     * Keeping this small prevents Shopify's GraphQL query
     * cost from exceeding the maximum allowed cost of 1000.
     */
    protected int $graphqlBatchSize = 5;

    /**
     * Maximum number of variants loaded per product.
     */
    protected int $variantsPerProduct = 100;

    /**
     * Maximum number of inventory levels loaded per inventory item.
     */
    protected int $inventoryLevelsPerItem = 50;

    public function __construct(
        protected ShopifyGraphQLService $shopify
    ) {
    }

    /**
     * Preview a vendor's inventory.
     *
     * No inventory is changed by this method.
     *
     * The FSWarehouse location is obtained from:
     *
     * shopify_tokens.fulfillment_location_id
     */
    public function preview(string $vendor): array
    {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        $shopifyToken = $this->getShopifyToken();

        /*
         * Get FSWarehouse from the location ID already
         * stored in the database.
         */
        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * ----------------------------------------------------------
         * STEP 1
         * ----------------------------------------------------------
         *
         * Get only the products belonging to this vendor.
         *
         * IMPORTANT:
         * We intentionally do NOT load variants or inventory
         * levels in this query.
         *
         * This keeps Shopify's GraphQL query cost very low.
         */
        $products = $this->getVendorProducts(
            $shopifyToken,
            $vendor
        );

        /*
         * ----------------------------------------------------------
         * STEP 2
         * ----------------------------------------------------------
         *
         * Load variants for the products in small batches.
         */
        $products = $this->loadVendorVariants(
            $shopifyToken,
            $products
        );

        /*
         * ----------------------------------------------------------
         * STEP 3
         * ----------------------------------------------------------
         *
         * Load inventory levels for all variants in small batches.
         */
        $products = $this->loadVendorInventoryLevels(
            $shopifyToken,
            $products
        );

        $variants = [];

        /*
         * ----------------------------------------------------------
         * STEP 4
         * ----------------------------------------------------------
         *
         * Build the preview.
         */
        foreach ($products as $product) {
            foreach (
                ($product['variants']['nodes'] ?? [])
                as $variant
            ) {
                $inventoryItem = $variant['inventoryItem'] ?? null;

                if (! $inventoryItem) {
                    continue;
                }

                $inventoryItemId = $inventoryItem['id'] ?? null;

                if (! $inventoryItemId) {
                    continue;
                }

                $inventoryLevels =
                    $inventoryItem['inventoryLevels']['nodes']
                    ?? [];

                $locations = [];

                $totalQuantity = 0;

                $fsQuantity = 0;

                $fsInventoryLevelId = null;

                foreach ($inventoryLevels as $level) {
                    $location = $level['location'] ?? null;

                    if (! $location) {
                        continue;
                    }

                    $locationId =
                        $location['id'] ?? null;

                    $locationName =
                        $location['name'] ?? '';

                    $inventoryLevelId =
                        $level['id'] ?? null;

                    $isActive =
                        (bool) (
                            $level['isActive']
                            ?? true
                        );

                    /*
                     * Explicitly read the "available" quantity.
                     */
                    $quantity =
                        $this->getAvailableQuantity($level);

                    /*
                     * Store all locations for the preview.
                     */
                    $locations[] = [
                        'inventory_level_id' =>
                            $inventoryLevelId,

                        'location_id' =>
                            $locationId,

                        'location_name' =>
                            $locationName,

                        'is_active' =>
                            $isActive,

                        'quantity' =>
                            $quantity,
                    ];

                    /*
                     * Only ACTIVE inventory locations should
                     * contribute to the total inventory.
                     *
                     * This prevents inactive Shopify inventory
                     * levels from being counted.
                     */
                    if ($isActive) {
                        $totalQuantity += $quantity;
                    }

                    /*
                     * Detect FSWarehouse using the actual
                     * location ID stored in the database.
                     */
                    if (
                        $locationId ===
                        $targetLocation['id']
                    ) {
                        $fsQuantity = $quantity;

                        $fsInventoryLevelId =
                            $inventoryLevelId;
                    }
                }

                $variants[] = [
                    'product_id' =>
                        $product['id'] ?? null,

                    'product_title' =>
                        $product['title'] ?? '',

                    'variant_id' =>
                        $variant['id'] ?? null,

                    'variant_title' =>
                        $variant['title'] ?? '',

                    'sku' =>
                        $variant['sku'] ?? '',

                    'inventory_item_id' =>
                        $inventoryItemId,

                    'locations' =>
                        $locations,

                    'fs_inventory_level_id' =>
                        $fsInventoryLevelId,

                    'fs_quantity' =>
                        $fsQuantity,

                    'total_quantity' =>
                        $totalQuantity,

                    /*
                     * This is the quantity that will exist
                     * at FSWarehouse after the move.
                     */
                    'new_fs_quantity' =>
                        $totalQuantity,
                ];
            }
        }

        return [
            'vendor' =>
                $vendor,

            'target_location' => [
                'id' =>
                    $targetLocation['id'],

                'name' =>
                    $targetLocation['name'],

                'is_active' =>
                    $targetLocation['isActive'] ?? true,
            ],

            'variants' =>
                $variants,

            'summary' => [
                'products' =>
                    count($products),

                'variants' =>
                    count($variants),

                'total_inventory' =>
                    collect($variants)
                        ->sum('total_quantity'),

                'fswarehouse_current_inventory' =>
                    collect($variants)
                        ->sum('fs_quantity'),

                'inventory_to_move' =>
                    collect($variants)
                        ->sum(
                            fn (array $variant) =>
                                max(
                                    0,
                                    $variant['total_quantity']
                                    -
                                    $variant['fs_quantity']
                                )
                        ),
            ],
        ];
    }

    /**
     * Move all available inventory for a vendor to FSWarehouse.
     *
     * For every variant:
     *
     * 1. Calculate total inventory across active locations.
     * 2. Make FSWarehouse active.
     * 3. Set FSWarehouse inventory to the total.
     * 4. Deactivate every other inventory location.
     *
     * Result:
     *
     * FSWarehouse = total inventory
     * Other locations = inactive
     */
    public function move(string $vendor): array
    {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        $shopifyToken = $this->getShopifyToken();

        /*
         * Always get the FSWarehouse location from the
         * database before performing the move.
         */
        $targetLocation =
            $this->getFswWarehouseLocation(
                $shopifyToken
            );

        /*
         * Get a fresh preview immediately before
         * modifying inventory.
         */
        $preview = $this->preview(
            $vendor
        );

        $results = [];

        foreach (
            $preview['variants']
            as $variant
        ) {
            $result = [
                'product_id' =>
                    $variant['product_id'],

                'product_title' =>
                    $variant['product_title'],

                'variant_id' =>
                    $variant['variant_id'],

                'variant_title' =>
                    $variant['variant_title'],

                'sku' =>
                    $variant['sku'],

                'inventory_item_id' =>
                    $variant['inventory_item_id'],

                'old_total_quantity' =>
                    $variant['total_quantity'],

                'old_fs_quantity' =>
                    $variant['fs_quantity'],

                'new_fs_quantity' =>
                    $variant['new_fs_quantity'],

                'success' =>
                    false,

                'message' =>
                    null,
            ];

            try {
                /*
                 * --------------------------------------------------
                 * STEP 1
                 * --------------------------------------------------
                 *
                 * Make FSWarehouse active.
                 */
                $this->activateInventoryAtLocation(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $targetLocation['id']
                );

                /*
                 * --------------------------------------------------
                 * STEP 2
                 * --------------------------------------------------
                 *
                 * Set the complete vendor inventory at
                 * FSWarehouse.
                 */
                $this->setInventoryQuantity(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $targetLocation['id'],
                    $variant['new_fs_quantity']
                );

                /*
                 * --------------------------------------------------
                 * STEP 3
                 * --------------------------------------------------
                 *
                 * Deactivate every other inventory location.
                 *
                 * This makes FSWarehouse the only active
                 * inventory location for this variant.
                 */
                $this->deactivateOtherLocations(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $variant['locations'],
                    $targetLocation['id']
                );

                $result['success'] = true;

                $result['message'] =
                    'Inventory moved to FSWarehouse successfully.';

                Log::info(
                    'Vendor inventory moved to FSWarehouse.',
                    [
                        'vendor' =>
                            $vendor,

                        'sku' =>
                            $variant['sku'],

                        'inventory_item_id' =>
                            $variant['inventory_item_id'],

                        'old_total_quantity' =>
                            $variant['total_quantity'],

                        'old_fs_quantity' =>
                            $variant['fs_quantity'],

                        'new_fs_quantity' =>
                            $variant['new_fs_quantity'],

                        'fswarehouse_location_id' =>
                            $targetLocation['id'],
                    ]
                );
            } catch (Throwable $e) {
                $result['message'] =
                    $e->getMessage();

                Log::error(
                    'Vendor inventory move failed.',
                    [
                        'vendor' =>
                            $vendor,

                        'sku' =>
                            $variant['sku'],

                        'inventory_item_id' =>
                            $variant['inventory_item_id'],

                        'exception' =>
                            $e,
                    ]
                );
            }

            $results[] = $result;
        }

        return [
            'vendor' =>
                $vendor,

            'target_location' => [
                'id' =>
                    $targetLocation['id'],

                'name' =>
                    $targetLocation['name'],
            ],

            'results' =>
                $results,

            'summary' => [
                'total' =>
                    count($results),

                'successful' =>
                    collect($results)
                        ->where(
                            'success',
                            true
                        )
                        ->count(),

                'failed' =>
                    collect($results)
                        ->where(
                            'success',
                            false
                        )
                        ->count(),
            ],
        ];
    }

    /**
     * Get FSWarehouse using the location ID already
     * stored in shopify_tokens.fulfillment_location_id.
     */
    protected function getFswWarehouseLocation(
        ShopifyToken $shopifyToken
    ): array {
        $locationId =
            $shopifyToken->fulfillment_location_id;

        if (blank($locationId)) {
            throw new RuntimeException(
                'FSWarehouse fulfillment location ID is not configured.'
            );
        }

        $query = <<<'GRAPHQL'
query GetFswWarehouseLocation(
    $id: ID!
) {
    location(id: $id) {
        id
        name
        isActive
    }
}
GRAPHQL;

        $data = $this->execute(
            $shopifyToken,
            $query,
            [
                'id' =>
                    $locationId,
            ]
        );

        $location =
            $data['location'] ?? null;

        if (! $location) {
            throw new RuntimeException(
                'The configured FSWarehouse location could not be found in Shopify. ' .
                'Location ID: ' .
                $locationId
            );
        }

        /*
         * Safety check.
         *
         * Accept:
         *
         * FSWarehouse
         * FS-Warehouse
         * FS Warehouse
         * FS_Warehouse
         */
        if (
            $this->normalizeLocationName(
                $location['name'] ?? ''
            )
            !==
            $this->normalizeLocationName(
                'FSWarehouse'
            )
        ) {
            throw new RuntimeException(
                'The configured fulfillment location is "' .
                ($location['name'] ?? 'Unknown') .
                '", not FSWarehouse. ' .
                'Location ID: ' .
                $locationId
            );
        }

        return $location;
    }

    /**
     * Normalize location names for safety checks.
     */
    protected function normalizeLocationName(
        string $name
    ): string {
        return strtolower(
            preg_replace(
                '/[^a-z0-9]/i',
                '',
                trim($name)
            )
        );
    }

    /**
     * Get all products for a vendor.
     *
     * IMPORTANT:
     *
     * This query ONLY loads:
     *
     * - Product ID
     * - Product title
     * - Vendor
     *
     * Variants and inventory are loaded separately.
     *
     * This is the main fix for Shopify's:
     *
     * MAX_COST_EXCEEDED
     */
    protected function getVendorProducts(
        ShopifyToken $shopifyToken,
        string $vendor
    ): array {
        $products = [];

        $cursor = null;

        do {
            $query = <<<'GRAPHQL'
query GetVendorProducts(
    $first: Int!
    $after: String
    $query: String
) {
    products(
        first: $first
        after: $after
        query: $query
    ) {
        nodes {
            id
            title
            vendor
        }

        pageInfo {
            hasNextPage
            endCursor
        }
    }
}
GRAPHQL;

            $data = $this->execute(
                $shopifyToken,
                $query,
                [
                    'first' =>
                        100,

                    'after' =>
                        $cursor,

                    'query' =>
                        'vendor:"' .
                        $this->escapeShopifySearchValue($vendor) .
                        '"',
                ]
            );

            $connection =
                $data['products'] ?? [];

            foreach (
                ($connection['nodes'] ?? [])
                as $product
            ) {
                /*
                 * Verify vendor exactly.
                 */
                if (
                    strcasecmp(
                        trim(
                            $product['vendor']
                            ?? ''
                        ),
                        trim($vendor)
                    ) !== 0
                ) {
                    continue;
                }

                $products[] = [
                    'id' =>
                        $product['id'] ?? null,

                    'title' =>
                        $product['title'] ?? '',

                    'vendor' =>
                        $product['vendor'] ?? '',

                    'variants' => [
                        'nodes' => [],
                    ],
                ];
            }

            $pageInfo =
                $connection['pageInfo']
                ?? [];

            $hasNextPage =
                (bool) (
                    $pageInfo['hasNextPage']
                    ?? false
                );

            $cursor =
                $pageInfo['endCursor']
                ?? null;
        } while (
            $hasNextPage
            &&
            $cursor
        );

        return $products;
    }

    /**
     * Load product variants in small GraphQL batches.
     *
     * This prevents one large nested products -> variants ->
     * inventoryLevels query from exceeding Shopify's query cost.
     */
    protected function loadVendorVariants(
        ShopifyToken $shopifyToken,
        array $products
    ): array {
        if (empty($products)) {
            return [];
        }

        $productChunks =
            array_chunk(
                $products,
                $this->graphqlBatchSize
            );

        foreach ($productChunks as $productChunk) {
            $productIds = array_values(
                array_filter(
                    array_map(
                        fn (array $product) =>
                            $product['id'] ?? null,
                        $productChunk
                    )
                )
            );

            if (empty($productIds)) {
                continue;
            }

            $query = <<<'GRAPHQL'
query LoadProductVariants(
    $ids: [ID!]!
    $first: Int!
) {
    nodes(ids: $ids) {
        ... on Product {
            id
            title

            variants(first: $first) {
                nodes {
                    id
                    title
                    sku

                    inventoryItem {
                        id
                    }
                }

                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
    }
}
GRAPHQL;

            $data = $this->execute(
                $shopifyToken,
                $query,
                [
                    'ids' =>
                        $productIds,

                    'first' =>
                        $this->variantsPerProduct,
                ]
            );

            foreach (
                ($data['nodes'] ?? [])
                as $productData
            ) {
                if (! $productData) {
                    continue;
                }

                $productId =
                    $productData['id']
                    ?? null;

                if (! $productId) {
                    continue;
                }

                /*
                 * Find the product in our existing array.
                 */
                foreach ($products as &$product) {
                    if (
                        ($product['id'] ?? null)
                        !==
                        $productId
                    ) {
                        continue;
                    }

                    $product['variants'] =
                        $productData['variants']
                        ?? [
                            'nodes' => [],
                        ];

                    break;
                }

                unset($product);
            }
        }

        /*
         * If a product has more than 100 variants,
         * load the remaining pages individually.
         *
         * This is uncommon, but prevents silently losing
         * variants for very large products.
         */
        foreach ($products as &$product) {
            $variantsConnection =
                $product['variants']
                ?? [];

            $pageInfo =
                $variantsConnection['pageInfo']
                ?? [];

            if (
                ! ($pageInfo['hasNextPage'] ?? false)
            ) {
                continue;
            }

            $allVariants =
                $variantsConnection['nodes']
                ?? [];

            $cursor =
                $pageInfo['endCursor']
                ?? null;

            while ($cursor) {
                $query = <<<'GRAPHQL'
query LoadMoreProductVariants(
    $id: ID!
    $first: Int!
    $after: String
) {
    product(id: $id) {
        variants(
            first: $first
            after: $after
        ) {
            nodes {
                id
                title
                sku

                inventoryItem {
                    id
                }
            }

            pageInfo {
                hasNextPage
                endCursor
            }
        }
    }
}
GRAPHQL;

                $data = $this->execute(
                    $shopifyToken,
                    $query,
                    [
                        'id' =>
                            $product['id'],

                        'first' =>
                            $this->variantsPerProduct,

                        'after' =>
                            $cursor,
                    ]
                );

                $connection =
                    $data['product']['variants']
                    ?? [];

                foreach (
                    ($connection['nodes'] ?? [])
                    as $variant
                ) {
                    $allVariants[] =
                        $variant;
                }

                $pageInfo =
                    $connection['pageInfo']
                    ?? [];

                if (
                    ! ($pageInfo['hasNextPage'] ?? false)
                ) {
                    $cursor = null;
                    break;
                }

                $cursor =
                    $pageInfo['endCursor']
                    ?? null;
            }

            $product['variants'] = [
                'nodes' =>
                    $allVariants,

                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ];
        }

        unset($product);

        return $products;
    }

    /**
     * Load inventory levels for all product variants
     * in small batches.
     */
    /**
 * Load inventory levels for all product variants.
 *
 * We query each InventoryItem directly instead of using
 * a large nested products -> variants -> inventoryLevels
 * query.
 *
 * This keeps the GraphQL query cost low and makes the
 * inventory level lookup more reliable.
 */
protected function loadVendorInventoryLevels(
    ShopifyToken $shopifyToken,
    array $products
): array {
    foreach ($products as &$product) {

        foreach (
            ($product['variants']['nodes'] ?? [])
            as &$variant
        ) {
            $inventoryItemId =
                $variant['inventoryItem']['id']
                ?? null;

            if (! $inventoryItemId) {
                continue;
            }

            /*
             * ------------------------------------------------------
             * Get inventory levels for this inventory item.
             * ------------------------------------------------------
             */
            $query = <<<'GRAPHQL'
query GetInventoryItemLevels(
    $id: ID!
) {
    inventoryItem(id: $id) {
        id

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

            pageInfo {
                hasNextPage
                endCursor
            }
        }
    }
}
GRAPHQL;

            $data = $this->execute(
                $shopifyToken,
                $query,
                [
                    'id' =>
                        $inventoryItemId,
                ]
            );

            $inventoryItem =
                $data['inventoryItem']
                ?? null;

            /*
             * If Shopify did not return the inventory item,
             * keep the existing ID but give us an empty
             * inventoryLevels structure.
             */
            if (! $inventoryItem) {
                Log::warning(
                    'Shopify inventory item not returned.',
                    [
                        'inventory_item_id' =>
                            $inventoryItemId,

                        'sku' =>
                            $variant['sku'] ?? null,

                        'product_id' =>
                            $product['id'] ?? null,
                    ]
                );

                $variant['inventoryItem'] = [
                    'id' =>
                        $inventoryItemId,

                    'inventoryLevels' => [
                        'nodes' => [],
                    ],
                ];

                continue;
            }

            /*
             * Store the complete InventoryItem response.
             */
            $variant['inventoryItem'] =
                $inventoryItem;

            /*
             * ------------------------------------------------------
             * Handle additional inventory level pages.
             * ------------------------------------------------------
             */
            $levelsConnection =
                $inventoryItem['inventoryLevels']
                ?? [];

            $allLevels =
                $levelsConnection['nodes']
                ?? [];

            $pageInfo =
                $levelsConnection['pageInfo']
                ?? [];

            $hasNextPage =
                (bool) (
                    $pageInfo['hasNextPage']
                    ?? false
                );

            $cursor =
                $pageInfo['endCursor']
                ?? null;

            while (
                $hasNextPage
                &&
                $cursor
            ) {
                $nextQuery = <<<'GRAPHQL'
query GetMoreInventoryItemLevels(
    $id: ID!
    $after: String
) {
    inventoryItem(id: $id) {
        id

        inventoryLevels(
            first: 50
            after: $after
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

            pageInfo {
                hasNextPage
                endCursor
            }
        }
    }
}
GRAPHQL;

                $nextData = $this->execute(
                    $shopifyToken,
                    $nextQuery,
                    [
                        'id' =>
                            $inventoryItemId,

                        'after' =>
                            $cursor,
                    ]
                );

                $nextConnection =
                    $nextData[
                        'inventoryItem'
                    ]['inventoryLevels']
                    ?? [];

                foreach (
                    ($nextConnection['nodes'] ?? [])
                    as $level
                ) {
                    $allLevels[] =
                        $level;
                }

                $nextPageInfo =
                    $nextConnection['pageInfo']
                    ?? [];

                $hasNextPage =
                    (bool) (
                        $nextPageInfo['hasNextPage']
                        ?? false
                    );

                $cursor =
                    $nextPageInfo['endCursor']
                    ?? null;
            }

            /*
             * Put the complete inventory levels back
             * onto the variant.
             */
            $variant['inventoryItem'][
                'inventoryLevels'
            ] = [
                'nodes' =>
                    $allLevels,

                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ];

            /*
             * Helpful logging while we verify the integration.
             */
            Log::info(
                'Shopify inventory levels loaded.',
                [
                    'inventory_item_id' =>
                        $inventoryItemId,

                    'sku' =>
                        $variant['sku'] ?? null,

                    'inventory_levels_count' =>
                        count($allLevels),

                    'inventory_levels' =>
                        $allLevels,
                ]
            );
        }

        unset($variant);
    }

    unset($product);

    return $products;
}

    /**
     * Get the "available" inventory quantity from
     * a Shopify inventory level.
     */
    protected function getAvailableQuantity(
        array $level
    ): int {
        foreach (
            ($level['quantities'] ?? [])
            as $quantity
        ) {
            if (
                ($quantity['name'] ?? null)
                ===
                'available'
            ) {
                return (int) (
                    $quantity['quantity']
                    ?? 0
                );
            }
        }

        return 0;
    }

    /**
     * Escape a Shopify product search value.
     */
    protected function escapeShopifySearchValue(
        string $value
    ): string {
        /*
         * Shopify search syntax uses quotes around the
         * vendor value. Escape backslash and quotes.
         */
        return str_replace(
            [
                '\\',
                '"',
            ],
            [
                '\\\\',
                '\\"',
            ],
            trim($value)
        );
    }

    /**
     * Activate FSWarehouse for an inventory item.
     *
     * Shopify's inventoryBulkToggleActivation mutation
     * can activate/deactivate locations for one inventory item.
     */
    protected function activateInventoryAtLocation(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        string $locationId
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventoryBulkToggleActivation(
    $inventoryItemId: ID!
    $inventoryItemUpdates: [InventoryBulkToggleActivationInput!]!
) {
    inventoryBulkToggleActivation(
        inventoryItemId: $inventoryItemId
        inventoryItemUpdates: $inventoryItemUpdates
    ) {
        inventoryItem {
            id
        }

        inventoryLevels {
            id

            quantities(
                names: ["available"]
            ) {
                name
                quantity
            }

            location {
                id
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

        $data = $this->execute(
            $shopifyToken,
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

        if (! empty($errors)) {
            $this->throwUserErrors(
                'Unable to activate FSWarehouse inventory',
                $errors
            );
        }
    }

    /**
     * Set absolute available inventory quantity.
     *
     * Shopify requires write_inventory for this mutation.
     */
    protected function setInventoryQuantity(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        string $locationId,
        int $quantity
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventorySetQuantities(
    $input: InventorySetQuantitiesInput!
) {
    inventorySetQuantities(
        input: $input
    ) {
        inventoryAdjustmentGroup {
            createdAt
            reason
            referenceDocumentUri

            changes {
                name
                delta
                quantityAfterChange
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

        $data = $this->execute(
            $shopifyToken,
            $mutation,
            [
                'input' => [
                    'name' =>
                        'available',

                    'reason' =>
                        'correction',

                    'referenceDocumentUri' =>
                        'vendor-inventory-move',

                    /*
                     * We intentionally use the absolute quantity
                     * calculated from the preview.
                     */
                    'ignoreCompareQuantity' =>
                        true,

                    'quantities' => [
                        [
                            'inventoryItemId' =>
                                $inventoryItemId,

                            'locationId' =>
                                $locationId,

                            'quantity' =>
                                $quantity,
                        ],
                    ],
                ],
            ]
        );

        $errors =
            $data[
                'inventorySetQuantities'
            ]['userErrors']
            ?? [];

        if (! empty($errors)) {
            $this->throwUserErrors(
                'Unable to set FSWarehouse inventory',
                $errors
            );
        }
    }

    /**
     * Deactivate all locations except FSWarehouse.
     *
     * We use inventoryBulkToggleActivation so Shopify removes
     * the inventory level and disables inventory at the old
     * location.
     */
    protected function deactivateOtherLocations(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        array $locations,
        string $fsLocationId
    ): void {
        $updates = [];

        foreach ($locations as $location) {
            $locationId =
                $location['location_id']
                ?? null;

            if (! $locationId) {
                continue;
            }

            /*
             * Never deactivate FSWarehouse.
             */
            if (
                $locationId ===
                $fsLocationId
            ) {
                continue;
            }

            /*
             * Only deactivate locations that are currently
             * active.
             */
            if (
                isset($location['is_active'])
                &&
                ! $location['is_active']
            ) {
                continue;
            }

            $updates[] = [
                'locationId' =>
                    $locationId,

                'activate' =>
                    false,
            ];
        }

        if (empty($updates)) {
            return;
        }

        $mutation = <<<'GRAPHQL'
mutation InventoryBulkToggleActivation(
    $inventoryItemId: ID!
    $inventoryItemUpdates: [InventoryBulkToggleActivationInput!]!
) {
    inventoryBulkToggleActivation(
        inventoryItemId: $inventoryItemId
        inventoryItemUpdates: $inventoryItemUpdates
    ) {
        inventoryItem {
            id
        }

        inventoryLevels {
            id

            location {
                id
                name
            }

            quantities(
                names: ["available"]
            ) {
                name
                quantity
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

        $data = $this->execute(
            $shopifyToken,
            $mutation,
            [
                'inventoryItemId' =>
                    $inventoryItemId,

                'inventoryItemUpdates' =>
                    $updates,
            ]
        );

        $errors =
            $data[
                'inventoryBulkToggleActivation'
            ]['userErrors']
            ?? [];

        if (! empty($errors)) {
            $this->throwUserErrors(
                'Unable to deactivate other inventory locations',
                $errors
            );
        }
    }

    /**
     * Convert Shopify user errors into a useful exception.
     */
    protected function throwUserErrors(
        string $prefix,
        array $errors
    ): void {
        $messages = [];

        foreach ($errors as $error) {
            $message =
                $error['message']
                ??
                'Unknown Shopify error';

            $field =
                $error['field']
                ?? null;

            $code =
                $error['code']
                ?? null;

            $parts = [];

            if ($field) {
                $parts[] =
                    'field: ' .
                    implode('.', $field);
            }

            if ($code) {
                $parts[] =
                    'code: ' .
                    $code;
            }

            $parts[] =
                'message: ' .
                $message;

            $messages[] =
                implode(', ', $parts);
        }

        throw new RuntimeException(
            $prefix .
            ': ' .
            implode('; ', $messages)
        );
    }

    /**
     * Execute a Shopify GraphQL request using the
     * existing ShopifyGraphQLService.
     */
    protected function execute(
        ShopifyToken $shopifyToken,
        string $query,
        array $variables = []
    ): array {
        return $this->shopify->executeWithCredentials(
            $shopifyToken->shop_domain,
            $shopifyToken->access_token,
            $query,
            $variables
        );
    }

    /**
     * Get the Shopify token.
     */
    protected function getShopifyToken(): ShopifyToken
    {
        $token =
            ShopifyToken::query()->first();

        if (! $token) {
            throw new RuntimeException(
                'Shopify connection was not found.'
            );
        }

        if (
            blank(
                $token->shop_domain
            )
        ) {
            throw new RuntimeException(
                'Shopify store domain is missing.'
            );
        }

        if (
            blank(
                $token->access_token
            )
        ) {
            throw new RuntimeException(
                'Shopify access token is missing.'
            );
        }

        if (
            blank(
                $token->fulfillment_location_id
            )
        ) {
            throw new RuntimeException(
                'FSWarehouse fulfillment location ID is missing from the database.'
            );
        }

        return $token;
    }
}