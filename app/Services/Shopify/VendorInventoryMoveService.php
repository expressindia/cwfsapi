<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
         * Get FSWarehouse from the location ID stored
         * in the database.
         */
        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * Get all products belonging to this vendor.
         */
        $products = $this->getVendorProducts(
            $shopifyToken,
            $vendor
        );

        Log::info(
            'Vendor inventory preview: products found.',
            [
                'vendor' => $vendor,
                'products_count' => count($products),
                'fswarehouse_location_id' => $targetLocation['id'],
                'fswarehouse_location_name' => $targetLocation['name'] ?? null,
            ]
        );

        $variants = [];

        foreach ($products as $product) {

            $productVariants =
                $product['variants']['nodes'] ?? [];

            foreach ($productVariants as $variant) {

                $inventoryItem =
                    $variant['inventoryItem'] ?? null;

                if (! $inventoryItem) {

                    Log::warning(
                        'Vendor inventory preview: inventory item missing.',
                        [
                            'vendor' => $vendor,
                            'product_id' => $product['id'] ?? null,
                            'product_title' => $product['title'] ?? null,
                            'variant_id' => $variant['id'] ?? null,
                            'sku' => $variant['sku'] ?? null,
                        ]
                    );

                    continue;
                }

                $inventoryItemId =
                    $inventoryItem['id'] ?? null;

                if (! $inventoryItemId) {
                    continue;
                }

                $inventoryLevels =
                    $inventoryItem['inventoryLevels']['nodes']
                    ?? [];

                $locations = [];

                /*
                 * Total inventory across ACTIVE locations only.
                 */
                $totalQuantity = 0;

                /*
                 * Current FSWarehouse available inventory.
                 */
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
                            ?? false
                        );

                    /*
                     * IMPORTANT:
                     *
                     * Explicitly find the "available"
                     * quantity instead of assuming
                     * quantities[0].
                     */
                    $quantity =
                        $this->getAvailableQuantity(
                            $level['quantities'] ?? []
                        );

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
                     * Only ACTIVE locations contribute
                     * to the inventory that will be moved.
                     */
                    if ($isActive) {
                        $totalQuantity += $quantity;
                    }

                    /*
                     * Identify FSWarehouse by its actual
                     * location ID.
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
                     * After the move, FSWarehouse will
                     * contain the complete active inventory.
                     */
                    'new_fs_quantity' =>
                        $totalQuantity,
                ];
            }
        }

        $totalInventory =
            collect($variants)
                ->sum('total_quantity');

        $fsWarehouseCurrentInventory =
            collect($variants)
                ->sum('fs_quantity');

        $inventoryToMove =
            collect($variants)
                ->sum(
                    fn (array $variant) =>
                        max(
                            0,
                            $variant['total_quantity']
                            -
                            $variant['fs_quantity']
                        )
                );

        Log::info(
            'Vendor inventory preview completed.',
            [
                'vendor' => $vendor,
                'products_count' => count($products),
                'variants_count' => count($variants),
                'total_inventory' => $totalInventory,
                'fswarehouse_current_inventory' =>
                    $fsWarehouseCurrentInventory,
                'inventory_to_move' =>
                    $inventoryToMove,
            ]
        );

        return [
            'vendor' =>
                $vendor,

            /*
             * Keep both keys so your existing Blade can
             * use either structure.
             */
            'target_location' => [
                'id' =>
                    $targetLocation['id'],

                'name' =>
                    $targetLocation['name'],

                'is_active' =>
                    $targetLocation['isActive'] ?? true,
            ],

            'fs_warehouse' => [
                'id' =>
                    $targetLocation['id'],

                'name' =>
                    $targetLocation['name'],

                'is_active' =>
                    $targetLocation['isActive'] ?? true,
            ],

            'variants' =>
                $variants,

            /*
             * Keep the top-level values used by
             * your current preview UI.
             */
            'fs_quantity' =>
                $fsWarehouseCurrentInventory,

            'total_quantity' =>
                $totalInventory,

            'new_fs_quantity' =>
                $totalInventory,

            'summary' => [
                'products' =>
                    count($products),

                'variants' =>
                    count($variants),

                'total_inventory' =>
                    $totalInventory,

                'fswarehouse_current_inventory' =>
                    $fsWarehouseCurrentInventory,

                'inventory_to_move' =>
                    $inventoryToMove,
            ],
        ];
    }

    /**
     * Move all available inventory for a vendor
     * to FSWarehouse.
     *
     * For every variant:
     *
     * 1. Activate FSWarehouse.
     * 2. Set FSWarehouse to the total active inventory.
     * 3. Deactivate every other active location.
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

        /*
         * Get the FSWarehouse location again immediately
         * before modifying inventory.
         */
        $targetLocation =
            $this->getFswWarehouseLocation(
                $shopifyToken
            );

        /*
         * Always obtain a fresh preview before moving
         * inventory.
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
                 * Set complete active inventory at FSWarehouse.
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
     * Shopify supports vendor filtering through the
     * products query. We also verify the vendor exactly
     * in PHP.
     */
    protected function getVendorProducts(
        ShopifyToken $shopifyToken,
        string $vendor
    ): array {

        $products = [];

        $cursor = null;

        /*
         * Shopify search syntax supports:
         *
         * vendor:Snowdevil
         *
         * For vendors containing spaces/punctuation,
         * quote the vendor value.
         */
        $vendorSearch =
            'vendor:"' .
            str_replace(
                '"',
                '\\"',
                $vendor
            ) .
            '"';

        Log::info(
            'Vendor inventory: searching Shopify products.',
            [
                'vendor' => $vendor,
                'query' => $vendorSearch,
            ]
        );

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
        sortKey: ID
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
                        tracked

                        inventoryLevels(
                            first: 250
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
                            100,

                        'after' =>
                            $cursor,

                        'query' =>
                            $vendorSearch,
                    ]
                );

            $connection =
                $data['products'] ?? [];

            $nodes =
                $connection['nodes'] ?? [];

            Log::info(
                'Vendor inventory: Shopify product page received.',
                [
                    'vendor' => $vendor,
                    'page_product_count' => count($nodes),
                ]
            );

            foreach ($nodes as $product) {

                /*
                 * Verify the vendor exactly.
                 *
                 * This prevents a broad Shopify search result
                 * from accidentally moving another vendor's
                 * inventory.
                 */
                $productVendor =
                    trim(
                        (string) (
                            $product['vendor']
                            ?? ''
                        )
                    );

                if (
                    strcasecmp(
                        $productVendor,
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

        Log::info(
            'Vendor inventory: Shopify vendor search completed.',
            [
                'vendor' => $vendor,
                'products_count' => count($products),
            ]
        );

        return $products;
    }

    /**
     * Get the "available" quantity from Shopify.
     *
     * Do NOT rely on quantities[0].
     */
    protected function getAvailableQuantity(
        array $quantities
    ): int {

        foreach ($quantities as $quantity) {

            if (
                strtolower(
                    trim(
                        (string) (
                            $quantity['name']
                            ?? ''
                        )
                    )
                )
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

                        /*
                         * We intentionally set an absolute
                         * quantity.
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
             * Only deactivate locations that
             * are currently active.
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