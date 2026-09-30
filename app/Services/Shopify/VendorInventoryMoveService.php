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
     * Preview all inventory for a vendor.
     *
     * This:
     * - Dynamically finds FSWarehouse by name.
     * - Finds products belonging to the supplied vendor.
     * - Gets all variants.
     * - Gets inventory quantities for all locations.
     * - Calculates the total inventory.
     *
     * No inventory is changed here.
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
         * Find FSWarehouse dynamically.
         */
        $locations = $this->getLocations(
            $shopifyToken
        );

        $targetLocation = $this->findFswWarehouse(
            $locations
        );

        if (! $targetLocation) {
            $availableLocations = collect($locations)
                ->pluck('name')
                ->filter()
                ->values()
                ->implode(', ');

            throw new RuntimeException(
                'FSWarehouse location was not found. ' .
                'Available Shopify locations: ' .
                ($availableLocations ?: 'none')
            );
        }

        /*
         * Get products for the vendor.
         */
        $products = $this->getVendorProducts(
            $shopifyToken,
            $vendor
        );

        $variants = [];

        foreach ($products as $product) {
            foreach (($product['variants']['nodes'] ?? []) as $variant) {
                $inventoryItem = $variant['inventoryItem'] ?? null;

                if (! $inventoryItem) {
                    continue;
                }

                $inventoryItemId = $inventoryItem['id'] ?? null;

                if (! $inventoryItemId) {
                    continue;
                }

                $levels = $inventoryItem['inventoryLevels']['nodes'] ?? [];

                $locationsInventory = [];

                $totalQuantity = 0;
                $fsQuantity = 0;

                foreach ($levels as $level) {
                    $location = $level['location'] ?? null;

                    if (! $location) {
                        continue;
                    }

                    $locationId = $location['id'] ?? null;
                    $locationName = $location['name'] ?? '';

                    $quantity = (int) (
                        $level['quantities'][0]['quantity']
                        ?? 0
                    );

                    /*
                     * Keep the quantity information for preview.
                     */
                    $locationsInventory[] = [
                        'location_id' => $locationId,
                        'location_name' => $locationName,
                        'quantity' => $quantity,
                    ];

                    /*
                     * Total all locations.
                     */
                    $totalQuantity += $quantity;

                    /*
                     * Detect FSWarehouse by normalized name.
                     */
                    if (
                        $this->normalizeLocationName($locationName)
                        === $this->normalizeLocationName('FSWarehouse')
                    ) {
                        $fsQuantity = $quantity;
                    }
                }

                $variants[] = [
                    'product_id' => $product['id'] ?? null,
                    'product_title' => $product['title'] ?? '',
                    'variant_id' => $variant['id'] ?? null,
                    'variant_title' => $variant['title'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'inventory_item_id' => $inventoryItemId,

                    'locations' => $locationsInventory,

                    'fs_quantity' => $fsQuantity,
                    'total_quantity' => $totalQuantity,

                    /*
                     * This is what FSWarehouse should contain
                     * after the move.
                     */
                    'new_fs_quantity' => $totalQuantity,
                ];
            }
        }

        return [
            'vendor' => $vendor,

            'target_location' => [
                'id' => $targetLocation['id'],
                'name' => $targetLocation['name'],
            ],

            'locations' => $locations,

            'variants' => $variants,

            'summary' => [
                'products' => count($products),
                'variants' => count($variants),
                'total_inventory' => collect($variants)
                    ->sum('total_quantity'),
            ],
        ];
    }

    /**
     * Move the vendor inventory to FSWarehouse.
     *
     * FSWarehouse receives the combined inventory from all locations.
     *
     * All other active inventory levels are then deactivated.
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
         * Find locations dynamically.
         */
        $locations = $this->getLocations(
            $shopifyToken
        );

        $targetLocation = $this->findFswWarehouse(
            $locations
        );

        if (! $targetLocation) {
            $availableLocations = collect($locations)
                ->pluck('name')
                ->filter()
                ->values()
                ->implode(', ');

            throw new RuntimeException(
                'FSWarehouse location was not found. ' .
                'Available Shopify locations: ' .
                ($availableLocations ?: 'none')
            );
        }

        /*
         * Get a fresh preview immediately before moving.
         *
         * This prevents using stale quantities from an old preview.
         */
        $preview = $this->preview($vendor);

        $results = [];

        foreach ($preview['variants'] as $variant) {
            $result = [
                'sku' => $variant['sku'],
                'product_title' => $variant['product_title'],
                'variant_title' => $variant['variant_title'],
                'inventory_item_id' => $variant['inventory_item_id'],
                'old_total_quantity' => $variant['total_quantity'],
                'new_fs_quantity' => $variant['new_fs_quantity'],
                'success' => false,
                'message' => null,
            ];

            try {
                /*
                 * Make sure FSWarehouse is active for this
                 * inventory item.
                 */
                $this->activateInventoryAtLocation(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $targetLocation['id']
                );

                /*
                 * Set the complete inventory quantity at
                 * FSWarehouse.
                 */
                $this->setInventoryQuantity(
                    $shopifyToken,
                    $variant['inventory_item_id'],
                    $targetLocation['id'],
                    $variant['new_fs_quantity']
                );

                /*
                 * Deactivate all other locations.
                 */
                foreach ($variant['locations'] as $location) {
                    $locationId = $location['location_id'];

                    if (! $locationId) {
                        continue;
                    }

                    if (
                        $locationId === $targetLocation['id']
                    ) {
                        continue;
                    }

                    $this->deactivateInventoryAtLocation(
                        $shopifyToken,
                        $variant['inventory_item_id'],
                        $locationId
                    );
                }

                $result['success'] = true;
                $result['message'] =
                    'Inventory moved to FSWarehouse successfully.';

                Log::info(
                    'Vendor inventory moved to FSWarehouse.',
                    [
                        'vendor' => $vendor,
                        'sku' => $variant['sku'],
                        'inventory_item_id' =>
                            $variant['inventory_item_id'],
                        'fswarehouse_quantity' =>
                            $variant['new_fs_quantity'],
                        'target_location_id' =>
                            $targetLocation['id'],
                    ]
                );
            } catch (\Throwable $e) {
                $result['message'] = $e->getMessage();

                Log::error(
                    'Failed to move vendor inventory.',
                    [
                        'vendor' => $vendor,
                        'sku' => $variant['sku'],
                        'inventory_item_id' =>
                            $variant['inventory_item_id'],
                        'exception' => $e,
                    ]
                );
            }

            $results[] = $result;
        }

        return [
            'vendor' => $vendor,

            'target_location' => [
                'id' => $targetLocation['id'],
                'name' => $targetLocation['name'],
            ],

            'results' => $results,

            'summary' => [
                'total' => count($results),

                'successful' => collect($results)
                    ->where('success', true)
                    ->count(),

                'failed' => collect($results)
                    ->where('success', false)
                    ->count(),
            ],
        ];
    }

    /**
     * Get Shopify locations.
     */
    protected function getLocations(
        ShopifyToken $shopifyToken
    ): array {
        $query = <<<'GRAPHQL'
query GetLocations(
    $first: Int!
) {
    locations(first: $first) {
        nodes {
            id
            name
            isActive
        }
    }
}
GRAPHQL;

        $data = $this->execute(
            $shopifyToken,
            $query,
            [
                'first' => 250,
            ]
        );

        return $data['locations']['nodes'] ?? [];
    }

    /**
     * Find FSWarehouse dynamically.
     *
     * Matching ignores:
     * - spaces
     * - hyphens
     * - underscores
     * - capitalization
     */
    protected function findFswWarehouse(
        array $locations
    ): ?array {
        $expected = $this->normalizeLocationName(
            'FSWarehouse'
        );

        foreach ($locations as $location) {
            $name = $location['name'] ?? '';

            if (
                $this->normalizeLocationName($name)
                === $expected
            ) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Normalize a Shopify location name.
     *
     * Examples:
     *
     * FSWarehouse
     * FS-Warehouse
     * FS Warehouse
     * FS_Warehouse
     * fswarehouse
     *
     * all become:
     *
     * fswarehouse
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
     * Get products belonging to a vendor.
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

                        inventoryLevels(first: 250) {
                            nodes {
                                id

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

            $data = $this->execute(
                $shopifyToken,
                $query,
                [
                    'first' => 100,
                    'after' => $cursor,
                    'query' => 'vendor:"' . $vendor . '"',
                ]
            );

            $connection =
                $data['products']
                ?? [];

            foreach (
                ($connection['nodes'] ?? [])
                as $product
            ) {
                /*
                 * Shopify's search query should filter this,
                 * but also verify vendor exactly here.
                 */
                if (
                    strcasecmp(
                        trim($product['vendor'] ?? ''),
                        trim($vendor)
                    ) !== 0
                ) {
                    continue;
                }

                $products[] = $product;
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
     * Activate an inventory item at a location.
     */
    protected function activateInventoryAtLocation(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        string $locationId
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventoryActivate(
    $inventoryItemId: ID!
    $locationId: ID!
) {
    inventoryActivate(
        inventoryItemId: $inventoryItemId
        locationId: $locationId
        available: 0
    ) {
        inventoryLevel {
            id
            quantities(names: ["available"]) {
                name
                quantity
            }
        }

        userErrors {
            field
            message
        }
    }
}
GRAPHQL;

        $data = $this->execute(
            $shopifyToken,
            $mutation,
            [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
            ]
        );

        $errors =
            $data['inventoryActivate']['userErrors']
            ?? [];

        if (! empty($errors)) {
            /*
             * If Shopify says it is already active,
             * that is okay.
             */
            $messages = collect($errors)
                ->pluck('message')
                ->implode('; ');

            if (
                stripos(
                    $messages,
                    'already'
                ) === false
            ) {
                throw new RuntimeException(
                    'Unable to activate inventory at FSWarehouse: ' .
                    $messages
                );
            }
        }
    }

    /**
     * Set inventory quantity at a location.
     */
    protected function setInventoryQuantity(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        string $locationId,
        int $quantity
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventorySetQuantity(
    $input: InventorySetQuantitiesInput!
) {
    inventorySetQuantities(
        input: $input
    ) {
        inventoryAdjustmentGroup {
            createdAt
            reason
            referenceDocumentUri
        }

        userErrors {
            field
            message
        }
    }
}
GRAPHQL;

        $data = $this->execute(
            $shopifyToken,
            $mutation,
            [
                'input' => [
                    'name' => 'available',

                    'reason' => 'correction',

                    'referenceDocumentUri' =>
                        'vendor-inventory-move',

                    'quantities' => [
                        [
                            'inventoryItemId' =>
                                $inventoryItemId,

                            'locationId' =>
                                $locationId,

                            'quantity' =>
                                $quantity,

                            'compareQuantity' =>
                                null,
                        ],
                    ],
                ],
            ]
        );

        $errors =
            $data['inventorySetQuantities']['userErrors']
            ?? [];

        if (! empty($errors)) {
            $messages = collect($errors)
                ->pluck('message')
                ->implode('; ');

            throw new RuntimeException(
                'Unable to set FSWarehouse inventory: ' .
                $messages
            );
        }
    }

    /**
     * Deactivate inventory at a non-FSWarehouse location.
     */
    protected function deactivateInventoryAtLocation(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        string $locationId
    ): void {
        /*
         * First set the quantity to zero.
         */
        $this->setInventoryQuantity(
            $shopifyToken,
            $inventoryItemId,
            $locationId,
            0
        );

        /*
         * Then deactivate the inventory item at this location.
         */
        $mutation = <<<'GRAPHQL'
mutation InventoryDeactivate(
    $inventoryItemId: ID!
    $locationId: ID!
) {
    inventoryDeactivate(
        inventoryItemId: $inventoryItemId
        locationId: $locationId
    ) {
        userErrors {
            field
            message
        }
    }
}
GRAPHQL;

        $data = $this->execute(
            $shopifyToken,
            $mutation,
            [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
            ]
        );

        $errors =
            $data['inventoryDeactivate']['userErrors']
            ?? [];

        if (! empty($errors)) {
            $messages = collect($errors)
                ->pluck('message')
                ->implode('; ');

            throw new RuntimeException(
                'Unable to deactivate inventory location: ' .
                $messages
            );
        }
    }

    /**
     * Execute GraphQL using the existing ShopifyGraphQLService.
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
     * Get the configured Shopify connection.
     */
    protected function getShopifyToken(): ShopifyToken
    {
        $token = ShopifyToken::query()->first();

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

        return $token;
    }
}