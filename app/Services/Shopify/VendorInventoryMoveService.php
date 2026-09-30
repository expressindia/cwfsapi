<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VendorInventoryMoveService
{
    /**
     * Number of products to retrieve per Shopify request.
     *
     * This query is intentionally shallow.
     */
    protected const PRODUCT_PAGE_SIZE = 100;

    /**
     * Number of variants to retrieve per Shopify request.
     */
    protected const VARIANT_PAGE_SIZE = 100;

    /**
     * Number of inventory items to inspect in one request.
     *
     * Keeping this small prevents the nested inventoryLevels
     * connection from becoming too expensive.
     */
    protected const INVENTORY_BATCH_SIZE = 5;

    /**
     * Maximum inventory levels requested for each inventory item.
     *
     * Most Shopify stores have far fewer locations than this.
     */
    protected const INVENTORY_LEVEL_PAGE_SIZE = 50;

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
         * Get all products belonging to this vendor.
         *
         * This method now uses several small GraphQL requests
         * instead of one deeply nested query.
         */
        $products = $this->getVendorProducts(
            $shopifyToken,
            $vendor
        );

        $variants = [];

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
                    $location =
                        $level['location'] ?? null;

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

                    $quantity =
                        (int) (
                            $level['quantities'][0]['quantity']
                            ?? 0
                        );

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
                     * We are moving the available inventory.
                     */
                    $totalQuantity += $quantity;

                    /*
                     * Detect FSWarehouse using the actual
                     * location ID stored in the database.
                     */
                    if (
                        $locationId ===
                        $targetLocation['id']
                    ) {
                        $fsQuantity =
                            $quantity;

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
                 * ------------------------------------------------------
                 * STEP 1
                 * ------------------------------------------------------
                 *
                 * Make FSWarehouse active.
                 */
                $this->activateInventoryAtLocation(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $targetLocation['id']
                );

                /*
                 * ------------------------------------------------------
                 * STEP 2
                 * ------------------------------------------------------
                 *
                 * Set the complete vendor inventory at FSWarehouse.
                 */
                $this->setInventoryQuantity(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $targetLocation['id'],
                    $variant['new_fs_quantity']
                );

                /*
                 * ------------------------------------------------------
                 * STEP 3
                 * ------------------------------------------------------
                 *
                 * Deactivate every other inventory location.
                 *
                 * This makes FSWarehouse the ONLY active
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
            } catch (\Throwable $e) {
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
     * We intentionally do NOT query:
     *
     * products
     *   -> variants
     *      -> inventoryLevels
     *
     * in one GraphQL request.
     *
     * Instead:
     *
     * 1. Get vendor products only.
     * 2. Get variants for those products.
     * 3. Get inventory levels in small batches.
     *
     * This prevents Shopify's MAX_COST_EXCEEDED error.
     */
    protected function getVendorProducts(
        ShopifyToken $shopifyToken,
        string $vendor
    ): array {
        /*
         * ----------------------------------------------------------
         * STEP 1
         * ----------------------------------------------------------
         *
         * Get vendor products without variants or inventory.
         */
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
                        self::PRODUCT_PAGE_SIZE,

                    'after' =>
                        $cursor,

                    'query' =>
                        $this->buildVendorSearchQuery(
                            $vendor
                        ),
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

                $products[
                    $product['id']
                ] = [
                    'id' =>
                        $product['id'],

                    'title' =>
                        $product['title']
                        ?? '',

                    'vendor' =>
                        $product['vendor']
                        ?? '',

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

        if (empty($products)) {
            return [];
        }

        /*
         * ----------------------------------------------------------
         * STEP 2
         * ----------------------------------------------------------
         *
         * Get variants for the vendor products.
         *
         * Shopify supports product_ids in the productVariants
         * search query.
         *
         * We send product IDs in groups instead of requesting
         * variants and inventory under every product.
         */
        $productIds = array_keys($products);

        foreach (
            array_chunk(
                $productIds,
                100
            )
            as $productIdBatch
        ) {
            $this->loadProductVariants(
                $shopifyToken,
                $products,
                $productIdBatch
            );
        }

        /*
         * ----------------------------------------------------------
         * STEP 3
         * ----------------------------------------------------------
         *
         * Get inventory levels separately in very small batches.
         */
        $inventoryItems = [];

        foreach ($products as $product) {
            foreach (
                ($product['variants']['nodes'] ?? [])
                as $variant
            ) {
                $inventoryItem =
                    $variant['inventoryItem']
                    ?? null;

                if (! $inventoryItem) {
                    continue;
                }

                $inventoryItemId =
                    $inventoryItem['id']
                    ?? null;

                if (! $inventoryItemId) {
                    continue;
                }

                $inventoryItems[
                    $inventoryItemId
                ] = true;
            }
        }

        $inventoryItemIds =
            array_keys($inventoryItems);

        foreach (
            array_chunk(
                $inventoryItemIds,
                self::INVENTORY_BATCH_SIZE
            )
            as $inventoryItemBatch
        ) {
            $inventoryData =
                $this->getInventoryLevelsForItems(
                    $shopifyToken,
                    $inventoryItemBatch
                );

            foreach (
                $inventoryData
                as $inventoryItemId => $inventoryLevels
            ) {
                foreach ($products as &$product) {
                    foreach (
                        ($product['variants']['nodes'] ?? [])
                        as &$variant
                    ) {
                        if (
                            ($variant['inventoryItem']['id'] ?? null)
                            !==
                            $inventoryItemId
                        ) {
                            continue;
                        }

                        $variant['inventoryItem'][
                            'inventoryLevels'
                        ] = [
                            'nodes' =>
                                $inventoryLevels,
                        ];

                        unset($variant);

                        break;
                    }

                    unset($product);
                }
            }
        }

        unset($product);

        return array_values($products);
    }

    /**
     * Load product variants for a batch of product IDs.
     */
    protected function loadProductVariants(
        ShopifyToken $shopifyToken,
        array &$products,
        array $productIds
    ): void {
        /*
         * Convert Shopify GIDs into numeric IDs because
         * Shopify's product_ids search filter expects IDs.
         */
        $numericProductIds = [];

        foreach ($productIds as $productId) {
            $numericId =
                $this->extractShopifyNumericId(
                    $productId
                );

            if ($numericId !== null) {
                $numericProductIds[] =
                    $numericId;
            }
        }

        if (empty($numericProductIds)) {
            return;
        }

        $searchQuery =
            'product_ids:' .
            implode(
                ',',
                $numericProductIds
            );

        $cursor = null;

        do {
            $query = <<<'GRAPHQL'
query GetProductVariants(
    $first: Int!
    $after: String
    $query: String!
) {
    productVariants(
        first: $first
        after: $after
        query: $query
    ) {
        nodes {
            id
            title
            sku

            product {
                id
                title
                vendor
            }

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
GRAPHQL;

            $data = $this->execute(
                $shopifyToken,
                $query,
                [
                    'first' =>
                        self::VARIANT_PAGE_SIZE,

                    'after' =>
                        $cursor,

                    'query' =>
                        $searchQuery,
                ]
            );

            $connection =
                $data['productVariants']
                ?? [];

            foreach (
                ($connection['nodes'] ?? [])
                as $variant
            ) {
                $product =
                    $variant['product']
                    ?? null;

                if (! $product) {
                    continue;
                }

                $productId =
                    $product['id']
                    ?? null;

                if (! $productId) {
                    continue;
                }

                /*
                 * Extra safety check.
                 */
                if (
                    strcasecmp(
                        trim(
                            $product['vendor']
                            ?? ''
                        ),
                        trim(
                            $products[$productId]['vendor']
                            ?? ''
                        )
                    ) !== 0
                ) {
                    continue;
                }

                if (
                    ! isset(
                        $products[$productId]
                    )
                ) {
                    continue;
                }

                $products[$productId][
                    'variants'
                ]['nodes'][] = [
                    'id' =>
                        $variant['id']
                        ?? null,

                    'title' =>
                        $variant['title']
                        ?? '',

                    'sku' =>
                        $variant['sku']
                        ?? '',

                    'inventoryItem' =>
                        $variant['inventoryItem']
                        ?? null,
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
    }

    /**
     * Get inventory levels for a small batch of inventory items.
     *
     * We deliberately keep the batch small because inventoryLevels
     * is a nested connection for every inventory item.
     */
    protected function getInventoryLevelsForItems(
        ShopifyToken $shopifyToken,
        array $inventoryItemIds
    ): array {
        if (empty($inventoryItemIds)) {
            return [];
        }

        $query = <<<'GRAPHQL'
query GetInventoryLevels(
    $ids: [ID!]!
    $first: Int!
) {
    nodes(ids: $ids) {
        ... on InventoryItem {
            id

            inventoryLevels(first: $first) {
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
}
GRAPHQL;

        $data = $this->execute(
            $shopifyToken,
            $query,
            [
                'ids' =>
                    array_values(
                        $inventoryItemIds
                    ),

                'first' =>
                    self::INVENTORY_LEVEL_PAGE_SIZE,
            ]
        );

        $result = [];

        foreach (
            ($data['nodes'] ?? [])
            as $node
        ) {
            if (
                ! isset(
                    $node['id']
                )
            ) {
                continue;
            }

            $result[
                $node['id']
            ] =
                $node[
                    'inventoryLevels'
                ]['nodes']
                ?? [];
        }

        return $result;
    }

    /**
     * Build the Shopify vendor search query safely.
     */
    protected function buildVendorSearchQuery(
        string $vendor
    ): string {
        /*
         * Shopify search strings use double quotes around
         * the vendor value.
         *
         * Escape backslashes and quotes so vendor names
         * cannot break the search expression.
         */
        $vendor =
            str_replace(
                [
                    '\\',
                    '"',
                ],
                [
                    '\\\\',
                    '\\"',
                ],
                trim($vendor)
            );

        return 'vendor:"' .
            $vendor .
            '"';
    }

    /**
     * Extract the numeric ID from a Shopify GID.
     *
     * Example:
     *
     * gid://shopify/Product/123456
     *
     * becomes:
     *
     * 123456
     */
    protected function extractShopifyNumericId(
        string $gid
    ): ?string {
        if (
            preg_match(
                '/\/(\d+)$/',
                $gid,
                $matches
            )
        ) {
            return $matches[1];
        }

        /*
         * Also allow an already numeric ID.
         */
        if (
            preg_match(
                '/^\d+$/',
                $gid
            )
        ) {
            return $gid;
        }

        return null;
    }

    /**
     * Activate FSWarehouse for an inventory item.
     *
     * Shopify's inventoryBulkToggleActivation mutation
     * can activate/deactivate multiple locations for one
     * inventory item.
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
                ?? 'Unknown Shopify error';

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
                    implode(
                        '.',
                        $field
                    );
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
                implode(
                    ', ',
                    $parts
                );
        }

        throw new RuntimeException(
            $prefix .
            ': ' .
            implode(
                '; ',
                $messages
            )
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
        $data =
            $this->shopify->executeWithCredentials(
                $shopifyToken->shop_domain,
                $shopifyToken->access_token,
                $query,
                $variables
            );

        return $data;
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