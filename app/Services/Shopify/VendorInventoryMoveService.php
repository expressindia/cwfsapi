<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VendorInventoryMoveService
{
    /**
     * Keep GraphQL requests small enough to avoid MAX_COST_EXCEEDED.
     */
    protected const VARIANT_PAGE_SIZE = 100;

    /**
     * Number of inventory items requested in one inventory query.
     *
     * We intentionally keep this small because every inventory item
     * contains an inventoryLevels connection.
     */
    protected const INVENTORY_BATCH_SIZE = 5;

    /**
     * Maximum number of locations we expect to have for one item.
     */
    protected const INVENTORY_LEVEL_PAGE_SIZE = 50;

    public function __construct(
        protected ShopifyGraphQLService $shopify
    ) {
    }

    /**
     * Preview a vendor's inventory.
     *
     * No inventory is changed.
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
         * Get FSWarehouse using the location ID stored in DB.
         */
        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * Get vendor variants using Shopify's productVariants query.
         *
         * This is intentionally NOT:
         *
         * products -> variants -> inventoryLevels
         *
         * in one request.
         */
        $catalog = $this->getVendorVariants(
            $shopifyToken,
            $vendor
        );

        $products = $catalog['products'];

        $catalogVariants = $catalog['variants'];

        /*
         * Collect inventory item IDs.
         */
        $inventoryItemIds = [];

        foreach ($catalogVariants as $variant) {
            $inventoryItemId =
                $variant['inventory_item_id']
                ?? null;

            if ($inventoryItemId) {
                $inventoryItemIds[$inventoryItemId] = true;
            }
        }

        /*
         * Retrieve inventory levels in small batches.
         */
        $inventoryByItem = [];

        foreach (
            array_chunk(
                array_keys($inventoryItemIds),
                self::INVENTORY_BATCH_SIZE
            )
            as $inventoryItemBatch
        ) {
            $batchInventory =
                $this->getInventoryLevelsForItems(
                    $shopifyToken,
                    $inventoryItemBatch
                );

            foreach ($batchInventory as $itemId => $levels) {
                $inventoryByItem[$itemId] = $levels;
            }
        }

        $variants = [];

        foreach ($catalogVariants as $variant) {
            $inventoryItemId =
                $variant['inventory_item_id']
                ?? null;

            if (! $inventoryItemId) {
                continue;
            }

            $inventoryLevels =
                $inventoryByItem[$inventoryItemId]
                ?? [];

            $locations = [];

            $otherLocations = [];

            $totalQuantity = 0;

            $fsQuantity = 0;

            $otherQuantity = 0;

            $fsInventoryLevelId = null;

            foreach ($inventoryLevels as $level) {
                $location =
                    $level['location']
                    ?? null;

                if (! $location) {
                    continue;
                }

                $locationId =
                    $location['id']
                    ?? null;

                $locationName =
                    $location['name']
                    ?? '';

                $inventoryLevelId =
                    $level['id']
                    ?? null;

                $isActive =
                    (bool) (
                        $level['isActive']
                        ?? true
                    );

                /*
                 * inventoryLevels normally returns active levels only.
                 *
                 * Keep this check so inactive levels are never included
                 * accidentally if the query is changed later.
                 */
                if (! $isActive) {
                    continue;
                }

                $quantity =
                    (int) (
                        $level['quantities'][0]['quantity']
                        ?? 0
                    );

                $locationData = [
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
                 * Store every active location.
                 */
                $locations[] = $locationData;

                /*
                 * Calculate total inventory.
                 */
                $totalQuantity += $quantity;

                /*
                 * Separate FSWarehouse from other locations.
                 */
                if (
                    $locationId ===
                    $targetLocation['id']
                ) {
                    $fsQuantity =
                        $quantity;

                    $fsInventoryLevelId =
                        $inventoryLevelId;
                } else {
                    $otherQuantity +=
                        $quantity;

                    $otherLocations[] =
                        $locationData;
                }
            }

            /*
             * Find product information.
             */
            $product =
                $products[
                    $variant['product_id']
                ]
                ?? [];

            $variants[] = [
                'product_id' =>
                    $variant['product_id']
                    ?? null,

                'product_title' =>
                    $product['title']
                    ?? '',

                'variant_id' =>
                    $variant['variant_id']
                    ?? null,

                'variant_title' =>
                    $variant['variant_title']
                    ?? '',

                'sku' =>
                    $variant['sku']
                    ?? '',

                'inventory_item_id' =>
                    $inventoryItemId,

                'locations' =>
                    $locations,

                'other_locations' =>
                    $otherLocations,

                'fs_inventory_level_id' =>
                    $fsInventoryLevelId,

                'fs_quantity' =>
                    $fsQuantity,

                'other_quantity' =>
                    $otherQuantity,

                'total_quantity' =>
                    $totalQuantity,

                /*
                 * After moving:
                 *
                 * FSWarehouse = all available inventory.
                 */
                'new_fs_quantity' =>
                    $totalQuantity,
            ];
        }

        $totalInventory =
            array_sum(
                array_column(
                    $variants,
                    'total_quantity'
                )
            );

        $currentFsInventory =
            array_sum(
                array_column(
                    $variants,
                    'fs_quantity'
                )
            );

        $inventoryToMove =
            array_sum(
                array_column(
                    $variants,
                    'other_quantity'
                )
            );

        /*
         * Keep BOTH the old keys expected by your Blade
         * and the newer target_location/summary structure.
         */
        return [
            'vendor' =>
                $vendor,

            'fs_warehouse' => [
                'id' =>
                    $targetLocation['id'],

                'name' =>
                    $targetLocation['name'],

                'is_active' =>
                    $targetLocation['isActive']
                    ?? true,
            ],

            'target_location' => [
                'id' =>
                    $targetLocation['id'],

                'name' =>
                    $targetLocation['name'],

                'is_active' =>
                    $targetLocation['isActive']
                    ?? true,
            ],

            'product_count' =>
                count($products),

            'variant_count' =>
                count($variants),

            'total_inventory' =>
                $totalInventory,

            'fswarehouse_current_inventory' =>
                $currentFsInventory,

            'inventory_to_move' =>
                $inventoryToMove,

            'variants' =>
                $variants,

            'summary' => [
                'products' =>
                    count($products),

                'variants' =>
                    count($variants),

                'total_inventory' =>
                    $totalInventory,

                'fswarehouse_current_inventory' =>
                    $currentFsInventory,

                'inventory_to_move' =>
                    $inventoryToMove,
            ],
        ];
    }

    /**
     * Move all available vendor inventory to FSWarehouse.
     */
    public function move(string $vendor): array
    {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        $shopifyToken =
            $this->getShopifyToken();

        $targetLocation =
            $this->getFswWarehouseLocation(
                $shopifyToken
            );

        /*
         * Always retrieve a fresh preview immediately
         * before making inventory changes.
         */
        $preview =
            $this->preview($vendor);

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

                'old_other_quantity' =>
                    $variant['other_quantity'],

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
                 * Activate FSWarehouse.
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
                 * Put the COMPLETE inventory quantity at
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
                 * Deactivate every other active location.
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

                        'old_other_quantity' =>
                            $variant['other_quantity'],

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

            $results[] =
                $result;
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
                    count(
                        array_filter(
                            $results,
                            fn ($result) =>
                                $result['success'] === true
                        )
                    ),

                'failed' =>
                    count(
                        array_filter(
                            $results,
                            fn ($result) =>
                                $result['success'] === false
                        )
                    ),
            ],
        ];
    }

    /**
     * Get all variants belonging to a vendor.
     *
     * This is the important part that fixes the MAX_COST_EXCEEDED
     * problem.
     *
     * We use:
     *
     * productVariants(query: vendor:"...")
     *
     * rather than:
     *
     * products -> variants -> inventoryLevels
     */
    protected function getVendorVariants(
        ShopifyToken $shopifyToken,
        string $vendor
    ): array {
        $products = [];

        $variants = [];

        $cursor = null;

        do {
            $query = <<<'GRAPHQL'
query GetVendorVariants(
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

            $data =
                $this->execute(
                    $shopifyToken,
                    $query,
                    [
                        'first' =>
                            self::VARIANT_PAGE_SIZE,

                        'after' =>
                            $cursor,

                        'query' =>
                            $this->buildVendorSearchQuery(
                                $vendor
                            ),
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

                /*
                 * Extra exact vendor check.
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

                $productId =
                    $product['id']
                    ?? null;

                if (! $productId) {
                    continue;
                }

                $products[$productId] = [
                    'id' =>
                        $productId,

                    'title' =>
                        $product['title']
                        ?? '',

                    'vendor' =>
                        $product['vendor']
                        ?? '',
                ];

                $variants[] = [
                    'product_id' =>
                        $productId,

                    'variant_id' =>
                        $variant['id']
                        ?? null,

                    'variant_title' =>
                        $variant['title']
                        ?? '',

                    'sku' =>
                        $variant['sku']
                        ?? '',

                    'inventory_item_id' =>
                        $variant['inventoryItem']['id']
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

        return [
            'products' =>
                $products,

            'variants' =>
                $variants,
        ];
    }

    /**
     * Get inventory levels for a small batch of inventory items.
     *
     * Shopify returns active inventory levels by default.
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

            inventoryLevels(
                first: $first
                includeInactive: false
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
}
GRAPHQL;

        $data =
            $this->execute(
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
            $itemId =
                $node['id']
                ?? null;

            if (! $itemId) {
                continue;
            }

            $inventoryLevels =
                $node['inventoryLevels']
                ?? [];

            /*
             * A normal Shopify store should never approach
             * 50 inventory locations per item.
             *
             * If it does, fail rather than silently returning
             * incomplete inventory.
             */
            if (
                ($inventoryLevels['pageInfo']['hasNextPage'] ?? false)
                === true
            ) {
                throw new RuntimeException(
                    'More than ' .
                    self::INVENTORY_LEVEL_PAGE_SIZE .
                    ' inventory locations were found for inventory item ' .
                    $itemId .
                    '. Pagination of inventory levels is required.'
                );
            }

            $result[$itemId] =
                $inventoryLevels['nodes']
                ?? [];
        }

        return $result;
    }

    /**
     * Build Shopify vendor search query.
     */
    protected function buildVendorSearchQuery(
        string $vendor
    ): string {
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
     * Get FSWarehouse from the database-stored location ID.
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

        $data =
            $this->execute(
                $shopifyToken,
                $query,
                [
                    'id' =>
                        $locationId,
                ]
            );

        $location =
            $data['location']
            ?? null;

        if (! $location) {
            throw new RuntimeException(
                'The configured FSWarehouse location could not be found in Shopify. ' .
                'Location ID: ' .
                $locationId
            );
        }

        /*
         * Safety check.
         */
        if (
            $this->normalizeLocationName(
                $location['name']
                ?? ''
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
     * Normalize location name.
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
     * Activate FSWarehouse.
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

        userErrors {
            field
            message
            code
        }
    }
}
GRAPHQL;

        $data =
            $this->execute(
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
     * Set absolute available quantity.
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

        $data =
            $this->execute(
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
     * Deactivate every location except FSWarehouse.
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
             * Only deactivate active locations.
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

        userErrors {
            field
            message
            code
        }
    }
}
GRAPHQL;

        $data =
            $this->execute(
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
     * Convert Shopify userErrors to an exception.
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
     * Execute Shopify GraphQL using the existing service.
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
     * Get Shopify token.
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

        if (blank($token->shop_domain)) {
            throw new RuntimeException(
                'Shopify store domain is missing.'
            );
        }

        if (blank($token->access_token)) {
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