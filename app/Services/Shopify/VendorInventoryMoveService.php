<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class VendorInventoryMoveService
{
    public function __construct(
        protected ShopifyGraphQLService $shopify
    ) {
    }

    /**
     * Preview a vendor's inventory.
     *
     * No inventory is changed by this method.
     *
     * FSWarehouse is obtained from:
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
         * Get FSWarehouse using the location ID stored
         * in the database.
         */
        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * Get all products belonging to this vendor.
         *
         * Inventory levels are intentionally NOT loaded
         * inside this query because that can make the
         * Shopify GraphQL query too expensive.
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

                /*
                 * Load inventory levels separately for this
                 * inventory item.
                 */
                $inventoryLevels = $this->getInventoryLevels(
                    $shopifyToken,
                    $inventoryItemId
                );

                $locations = [];

                $totalQuantity = 0;

                $fsQuantity = 0;

                $fsInventoryLevelId = null;

                foreach ($inventoryLevels as $level) {
                    $location = $level['location'] ?? null;

                    if (! $location) {
                        continue;
                    }

                    $locationId = $location['id'] ?? null;

                    $locationName = $location['name'] ?? '';

                    $inventoryLevelId = $level['id'] ?? null;

                    $isActive = (bool) (
                        $level['isActive'] ?? false
                    );

                    /*
                     * Find the "available" quantity.
                     */
                    $quantity = 0;

                    foreach (
                        ($level['quantities'] ?? [])
                        as $quantityRow
                    ) {
                        if (
                            ($quantityRow['name'] ?? '')
                            === 'available'
                        ) {
                            $quantity = (int) (
                                $quantityRow['quantity'] ?? 0
                            );

                            break;
                        }
                    }

                    /*
                     * Store every inventory location.
                     */
                    $locations[] = [
                        'inventory_level_id' => $inventoryLevelId,
                        'location_id' => $locationId,
                        'location_name' => $locationName,
                        'is_active' => $isActive,
                        'quantity' => $quantity,
                    ];

                    /*
                     * Only count active inventory locations
                     * toward the total inventory that will
                     * be moved.
                     */
                    if ($isActive) {
                        $totalQuantity += $quantity;
                    }

                    /*
                     * Detect FSWarehouse by its actual
                     * location ID.
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
                     * Final quantity that should exist
                     * at FSWarehouse.
                     */
                    'new_fs_quantity' =>
                        $totalQuantity,
                ];
            }
        }

        return [
            'vendor' => $vendor,

            'target_location' => [
                'id' =>
                    $targetLocation['id'],

                'name' =>
                    $targetLocation['name'],

                'is_active' =>
                    $targetLocation['isActive'] ?? true,
            ],

            'variants' => $variants,

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
     * 1. Make FSWarehouse active.
     * 2. Set FSWarehouse inventory to total active inventory.
     * 3. Deactivate every other inventory location.
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
         * Get FSWarehouse location from database.
         */
        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * Always get a fresh preview immediately before
         * changing inventory.
         */
        $preview = $this->preview($vendor);

        $results = [];

        foreach ($preview['variants'] as $variant) {
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
                 *
                 * IMPORTANT:
                 *
                 * changeFromQuantity is the current FSWarehouse
                 * quantity from the fresh preview.
                 *
                 * This replaces the old invalid
                 * ignoreCompareQuantity field.
                 */
                $this->setInventoryQuantity(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $targetLocation['id'],
                    $variant['new_fs_quantity'],
                    $variant['fs_quantity']
                );

                /*
                 * ------------------------------------------------------
                 * STEP 3
                 * ------------------------------------------------------
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
     * Get FSWarehouse using the location ID stored in
     * shopify_tokens.fulfillment_location_id.
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
                'id' => $locationId,
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
     * Inventory levels are NOT requested here.
     * They are loaded separately per inventory item.
     *
     * This keeps Shopify GraphQL query cost lower.
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

            variants(first: 250) {
                nodes {
                    id
                    title
                    sku

                    inventoryItem {
                        id
                    }
                }
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
                        100,

                    'after' =>
                        $cursor,

                    'query' =>
                        'vendor:"' .
                        $vendor .
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

                $products[] =
                    $product;
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
     * Get all inventory levels for one inventory item.
     *
     * This query is deliberately separated from the vendor
     * product query to avoid Shopify GraphQL cost problems.
     */
    protected function getInventoryLevels(
        ShopifyToken $shopifyToken,
        string $inventoryItemId
    ): array {
        $levels = [];

        $cursor = null;

        do {
            $query = <<<'GRAPHQL'
query GetInventoryItemLevels(
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

            $data = $this->execute(
                $shopifyToken,
                $query,
                [
                    'id' =>
                        $inventoryItemId,

                    'after' =>
                        $cursor,
                ]
            );

            $inventoryItem =
                $data['inventoryItem']
                ?? null;

            if (! $inventoryItem) {
                return [];
            }

            $connection =
                $inventoryItem['inventoryLevels']
                ?? [];

            foreach (
                ($connection['nodes'] ?? [])
                as $level
            ) {
                $levels[] =
                    $level;
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

        Log::info(
            'Shopify inventory levels loaded.',
            [
                'inventory_item_id' =>
                    $inventoryItemId,

                'inventory_levels_count' =>
                    count($levels),

                'inventory_levels' =>
                    $levels,
            ]
        );

        return $levels;
    }

    /**
     * Activate FSWarehouse for an inventory item.
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
                name
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
     * IMPORTANT:
     *
     * The old code used:
     *
     * ignoreCompareQuantity => true
     *
     * That field is no longer valid for
     * InventorySetQuantitiesInput.
     *
     * We now use changeFromQuantity to perform
     * a compare-and-set operation.
     */
    protected function setInventoryQuantity(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        string $locationId,
        int $quantity,
        int $currentQuantity
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventorySetQuantities(
    $input: InventorySetQuantitiesInput!
    $idempotencyKey: String!
) {
    inventorySetQuantities(
        input: $input
    ) @idempotent(key: $idempotencyKey) {
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
                     * Compare-and-set:
                     *
                     * Shopify will only make the change if
                     * the current quantity still matches
                     * this value.
                     */
                    'quantities' => [
                        [
                            'inventoryItemId' =>
                                $inventoryItemId,

                            'locationId' =>
                                $locationId,

                            'quantity' =>
                                $quantity,

                            'changeFromQuantity' =>
                                $currentQuantity,
                        ],
                    ],
                ],

                /*
                 * Required for current Shopify API versions.
                 */
                'idempotencyKey' =>
                    Str::uuid()->toString(),
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