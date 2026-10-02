<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
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
     * IMPORTANT:
     *
     * This method does not change anything in Shopify.
     *
     * It only loads:
     *
     * - products
     * - variants
     * - inventory items
     * - inventory locations
     * - active/inactive status
     * - current quantities
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
         * in shopify_tokens.fulfillment_location_id.
         */
        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * Get products belonging to this vendor.
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
                 * Load inventory levels separately.
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
                     * Get available quantity.
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

                    $locations[] = [
                        'inventory_level_id' => $inventoryLevelId,
                        'location_id' => $locationId,
                        'location_name' => $locationName,
                        'is_active' => $isActive,
                        'quantity' => $quantity,
                    ];

                    /*
                     * We only calculate the total for
                     * display purposes.
                     *
                     * NO quantity will be changed.
                     */
                    if ($isActive) {
                        $totalQuantity += $quantity;
                    }

                    /*
                     * Identify FSWarehouse.
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
                     * Kept for compatibility with
                     * existing Blade code.
                     *
                     * This value is NOT written to Shopify.
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
     * Activate FSWarehouse for every vendor variant.
     *
     * IMPORTANT:
     *
     * This method ONLY changes the activation status.
     *
     * It DOES NOT:
     *
     * - set inventory quantity
     * - move inventory
     * - deactivate Headquarters
     */
    public function activate(string $vendor): array
    {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        $shopifyToken = $this->getShopifyToken();

        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * Always get a fresh preview.
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

                'success' =>
                    false,

                'already_active' =>
                    false,

                'message' =>
                    null,
            ];

            try {
                /*
                 * Check current FSWarehouse status.
                 */
                $fsLocation = collect(
                    $variant['locations'] ?? []
                )->first(
                    fn (array $location) =>
                        ($location['location_id'] ?? null)
                        ===
                        $targetLocation['id']
                );

                /*
                 * If already active, no mutation is needed.
                 */
                if (
                    $fsLocation
                    &&
                    ($fsLocation['is_active'] ?? false)
                ) {
                    $result['success'] = true;

                    $result['already_active'] = true;

                    $result['message'] =
                        'FSWarehouse is already active. No change was required.';
                } else {
                    /*
                     * Activate FSWarehouse.
                     */
                    $this->activateInventoryAtLocation(
                        $shopifyToken,
                        $variant['inventory_item_id'],
                        $targetLocation['id']
                    );

                    $result['success'] = true;

                    $result['message'] =
                        'FSWarehouse activated successfully.';
                }

                Log::info(
                    'FSWarehouse activated for vendor variant.',
                    [
                        'vendor' => $vendor,

                        'sku' =>
                            $variant['sku'],

                        'inventory_item_id' =>
                            $variant['inventory_item_id'],

                        'location_id' =>
                            $targetLocation['id'],
                    ]
                );
            } catch (Throwable $e) {
                $result['message'] =
                    $e->getMessage();

                Log::error(
                    'Unable to activate FSWarehouse.',
                    [
                        'vendor' => $vendor,

                        'sku' =>
                            $variant['sku'],

                        'inventory_item_id' =>
                            $variant['inventory_item_id'],

                        'exception' => $e,
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

                'already_active' =>
                    collect($results)
                        ->where(
                            'already_active',
                            true
                        )
                        ->count(),
            ],
        ];
    }

    /**
     * Verify that FSWarehouse is active for every
     * variant belonging to the vendor.
     *
     * This method does not change Shopify.
     */
    public function verifyActivation(string $vendor): array
    {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        /*
         * Get fresh Shopify data.
         */
        $preview = $this->preview($vendor);

        $targetLocation =
            $preview['target_location'];

        $results = [];

        foreach ($preview['variants'] as $variant) {
            $fsLocation = collect(
                $variant['locations'] ?? []
            )->first(
                fn (array $location) =>
                    ($location['location_id'] ?? null)
                    ===
                    $targetLocation['id']
            );

            $isActive = (bool) (
                $fsLocation['is_active']
                ?? false
            );

            $results[] = [
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

                'fswarehouse_active' =>
                    $isActive,

                'message' =>
                    $isActive
                        ? 'FSWarehouse is active.'
                        : 'FSWarehouse is not active.',
            ];
        }

        $total = count($results);

        $active = collect($results)
            ->where(
                'fswarehouse_active',
                true
            )
            ->count();

        $inactive = $total - $active;

        return [
            'vendor' =>
                $vendor,

            'target_location' =>
                $targetLocation,

            'verified' =>
                $total > 0
                && $inactive === 0,

            'results' =>
                $results,

            'summary' => [
                'total' =>
                    $total,

                'active' =>
                    $active,

                'inactive' =>
                    $inactive,
            ],
        ];
    }

    /**
     * Deactivate Headquarters for every vendor variant.
     *
     * IMPORTANT:
     *
     * - FSWarehouse is NEVER deactivated.
     * - Inventory quantities are NOT changed.
     * - Other locations are left untouched.
     *
     * Headquarters is recognized by:
     *
     * - Headquarters
     * - HQ
     */
    public function deactivate(string $vendor): array
    {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        $shopifyToken = $this->getShopifyToken();

        $targetLocation = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        /*
         * Always get fresh data.
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

                'success' =>
                    false,

                'headquarters_found' =>
                    false,

                'already_inactive' =>
                    false,

                'message' =>
                    null,
            ];

            try {
                /*
                 * Find Headquarters only.
                 */
                $headquarters = collect(
                    $variant['locations'] ?? []
                )->first(
                    function (array $location) use (
                        $targetLocation
                    ) {
                        $locationId =
                            $location['location_id']
                            ?? null;

                        $locationName =
                            $location['location_name']
                            ?? '';

                        /*
                         * Never target FSWarehouse.
                         */
                        if (
                            $locationId ===
                            $targetLocation['id']
                        ) {
                            return false;
                        }

                        return $this->isHeadquartersLocation(
                            $locationName
                        );
                    }
                );

                /*
                 * No Headquarters inventory level found.
                 */
                if (! $headquarters) {
                    $result['success'] = true;

                    $result['message'] =
                        'Headquarters location was not found for this variant. No change was required.';
                } elseif (
                    ! ($headquarters['is_active'] ?? false)
                ) {
                    $result['success'] = true;

                    $result['headquarters_found'] = true;

                    $result['already_inactive'] = true;

                    $result['message'] =
                        'Headquarters is already inactive.';
                } else {
                    /*
                     * Deactivate ONLY Headquarters.
                     */
                    $this->deactivateLocation(
                        $shopifyToken,
                        $variant['inventory_item_id'],
                        $headquarters['location_id']
                    );

                    $result['success'] = true;

                    $result['headquarters_found'] = true;

                    $result['message'] =
                        'Headquarters deactivated successfully.';
                }

                Log::info(
                    'Headquarters activation status updated.',
                    [
                        'vendor' =>
                            $vendor,

                        'sku' =>
                            $variant['sku'],

                        'inventory_item_id' =>
                            $variant['inventory_item_id'],
                    ]
                );
            } catch (Throwable $e) {
                $result['message'] =
                    $e->getMessage();

                Log::error(
                    'Unable to deactivate Headquarters.',
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

                'headquarters_found' =>
                    collect($results)
                        ->where(
                            'headquarters_found',
                            true
                        )
                        ->count(),

                'already_inactive' =>
                    collect($results)
                        ->where(
                            'already_inactive',
                            true
                        )
                        ->count(),
            ],
        ];
    }

    /**
     * Get FSWarehouse location.
     *
     * The ID comes from:
     *
     * shopify_tokens.fulfillment_location_id
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
     * Normalize a Shopify location name.
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
     * Check whether a location is Headquarters.
     *
     * Accepted names:
     *
     * - Headquarters
     * - HQ
     */
    protected function isHeadquartersLocation(
        string $locationName
    ): bool {
        $normalized =
            $this->normalizeLocationName(
                $locationName
            );

        return in_array(
            $normalized,
            [
                'headquarters',
                'hq',
            ],
            true
        );
    }

    /**
     * Get all products belonging to vendor.
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
                    'first' => 100,

                    'after' => $cursor,

                    'query' =>
                        'vendor:"' .
                        $vendor .
                        '"',
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
                 * Shopify vendor query can still return
                 * unexpected results, so verify exactly.
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
     * includeInactive=true is important because we need
     * to see the current activation state.
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
            ]
        );

        return $levels;
    }

    /**
     * Activate an inventory item at a location.
     *
     * IMPORTANT:
     *
     * This changes activation only.
     *
     * It does NOT change quantity.
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
     * Deactivate one specific inventory location.
     *
     * This method does NOT modify quantity.
     */
    protected function deactivateLocation(
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

        if (! empty($errors)) {
            $this->throwUserErrors(
                'Unable to deactivate Headquarters',
                $errors
            );
        }
    }

    /**
     * Convert Shopify user errors to RuntimeException.
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
                        (array) $field
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
     * Execute Shopify GraphQL request.
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
     * Get Shopify connection.
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