<?php

namespace App\Services\Shopify;

use App\Models\ShopifyToken;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VendorInventoryMoveService
{
    /**
     * Number of products displayed per page.
     */
    protected const PRODUCTS_PER_PAGE = 25;

    /**
     * Number of variants loaded in a single request when
     * performing a product-level action.
     */
    protected const VARIANTS_PER_PAGE = 250;

    /**
     * Supported product-level actions.
     */
    protected const ACTIONS = [
        'activate_fs',
        'activate_hq',
        'activate_both',
        'deactivate_fs',
        'deactivate_hq',
        'deactivate_both',
    ];

    public function __construct(
        protected ShopifyGraphQLService $shopify
    ) {
    }

    /**
     * Get one page of products for a vendor.
     *
     * IMPORTANT:
     * This method only READS inventory information.
     * It never changes quantities or activation status.
     *
     * @param string      $vendor
     * @param string|null $after
     * @param string|null $before
     *
     * @return array
     */
    public function getVendorProductPage(
        string $vendor,
        ?string $after = null,
        ?string $before = null
    ): array {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        if ($after !== null && $before !== null) {
            throw new RuntimeException(
                'Only one pagination cursor can be supplied.'
            );
        }

        $shopifyToken = $this->getShopifyToken();

        $fsWarehouse = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        $headquarters = $this->getHeadquartersLocation(
            $shopifyToken
        );

        $productQuery = $this->buildVendorQuery($vendor);

        /*
         * When moving forward:
         *
         * first = 25
         * after = current page end cursor
         *
         * When moving backward:
         *
         * last = 25
         * before = current page start cursor
         */
        $variables = [
            'first' => $before === null
                ? self::PRODUCTS_PER_PAGE
                : null,

            'last' => $before !== null
                ? self::PRODUCTS_PER_PAGE
                : null,

            'after' => $before === null
                ? $after
                : null,

            'before' => $before,

            'query' => $productQuery,

            'fsLocationId' => $fsWarehouse['id'],

            'hqLocationId' => $headquarters['id'],
        ];

        $query = <<<'GRAPHQL'
query VendorProducts(
    $first: Int
    $last: Int
    $after: String
    $before: String
    $query: String!
    $fsLocationId: ID!
    $hqLocationId: ID!
) {
    products(
        first: $first
        last: $last
        after: $after
        before: $before
        query: $query
        sortKey: TITLE
    ) {
        nodes {
            id
            title
            handle
            vendor

            featuredImage {
                url
                altText
            }

            variantsCount {
                count
            }

            variants(first: 250) {
                nodes {
                    id
                    title
                    sku

                    inventoryItem {
                        id

                        fsInventoryLevel: inventoryLevel(
                            locationId: $fsLocationId
                            includeInactive: true
                        ) {
                            id
                            isActive

                            quantities(names: ["available"]) {
                                name
                                quantity
                            }

                            location {
                                id
                                name
                            }
                        }

                        hqInventoryLevel: inventoryLevel(
                            locationId: $hqLocationId
                            includeInactive: true
                        ) {
                            id
                            isActive

                            quantities(names: ["available"]) {
                                name
                                quantity
                            }

                            location {
                                id
                                name
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

        pageInfo {
            hasNextPage
            hasPreviousPage
            startCursor
            endCursor
        }
    }
}
GRAPHQL;

        $response = $this->execute(
            $shopifyToken,
            $query,
            $variables
        );

        $productsConnection = $response['products'] ?? null;

        if (! is_array($productsConnection)) {
            throw new RuntimeException(
                'Shopify did not return the products connection.'
            );
        }

        $products = [];

        foreach (
            ($productsConnection['nodes'] ?? [])
            as $product
        ) {
            $products[] = $this->formatProductForListing(
                $product,
                $fsWarehouse,
                $headquarters
            );
        }

        /*
         * Get the total count separately.
         *
         * This is only one small count query and avoids
         * loading the entire vendor catalog into PHP.
         */
        $totalProducts = $this->getVendorProductCount(
            $shopifyToken,
            $productQuery
        );

        $pageInfo = $productsConnection['pageInfo'] ?? [];

        return [
            'vendor' => $vendor,

            'products' => $products,

            'total_products' => $totalProducts,

            'per_page' => self::PRODUCTS_PER_PAGE,

            'total_pages' => max(
                1,
                (int) ceil(
                    $totalProducts / self::PRODUCTS_PER_PAGE
                )
            ),

            'has_next_page' => (bool) (
                $pageInfo['hasNextPage'] ?? false
            ),

            'has_previous_page' => (bool) (
                $pageInfo['hasPreviousPage'] ?? false
            ),

            'next_cursor' => $pageInfo['endCursor'] ?? null,

            'previous_cursor' => $pageInfo['startCursor'] ?? null,

            'fswarehouse' => $fsWarehouse,

            'headquarters' => $headquarters,
        ];
    }

    /**
     * Count products for a vendor.
     */
    protected function getVendorProductCount(
        ShopifyToken $shopifyToken,
        string $productQuery
    ): int {
        $query = <<<'GRAPHQL'
query VendorProductsCount(
    $query: String!
) {
    productsCount(
        query: $query
        limit: 10000
    ) {
        count
    }
}
GRAPHQL;

        $response = $this->execute(
            $shopifyToken,
            $query,
            [
                'query' => $productQuery,
            ]
        );

        return (int) (
            $response['productsCount']['count'] ?? 0
        );
    }

    /**
     * Format one Shopify product for the Blade listing.
     *
     * This method only reads data.
     */
    protected function formatProductForListing(
        array $product,
        array $fsWarehouse,
        array $headquarters
    ): array {
        $variants = [];

        $fsActiveCount = 0;
        $fsInactiveCount = 0;

        $hqActiveCount = 0;
        $hqInactiveCount = 0;

        $fsQuantity = 0;
        $hqQuantity = 0;

        foreach (
            ($product['variants']['nodes'] ?? [])
            as $variant
        ) {
            $inventoryItem = $variant['inventoryItem'] ?? null;

            if (! $inventoryItem) {
                continue;
            }

            $fsLevel = $inventoryItem['fsInventoryLevel'] ?? null;
            $hqLevel = $inventoryItem['hqInventoryLevel'] ?? null;

            $fsIsActive = $fsLevel !== null
                && (bool) ($fsLevel['isActive'] ?? false);

            $hqIsActive = $hqLevel !== null
                && (bool) ($hqLevel['isActive'] ?? false);

            $fsVariantStatus = $fsIsActive
                ? 'active'
                : 'inactive';

            $hqVariantStatus = $hqIsActive
                ? 'active'
                : 'inactive';

            if ($fsIsActive) {
                $fsActiveCount++;
            } else {
                $fsInactiveCount++;
            }

            if ($hqIsActive) {
                $hqActiveCount++;
            } else {
                $hqInactiveCount++;
            }

            $fsVariantQuantity = $this->getAvailableQuantity(
                $fsLevel
            );

            $hqVariantQuantity = $this->getAvailableQuantity(
                $hqLevel
            );

            $fsQuantity += $fsVariantQuantity;
            $hqQuantity += $hqVariantQuantity;

            $variants[] = [
                'id' => $variant['id'] ?? null,

                'title' => $variant['title'] ?? '',

                'sku' => $variant['sku'] ?? '',

                'inventory_item_id' =>
                    $inventoryItem['id'] ?? null,

                'fs_status' => $fsVariantStatus,

                'fs_quantity' => $fsVariantQuantity,

                'hq_status' => $hqVariantStatus,

                'hq_quantity' => $hqVariantQuantity,
            ];
        }

        $variantCount = (int) (
            $product['variantsCount']['count']
            ?? count($variants)
        );

        /*
         * Determine the overall FSWarehouse status.
         */
        if ($fsActiveCount === 0) {
            $fsStatus = 'inactive';
        } elseif ($fsInactiveCount === 0) {
            $fsStatus = 'active';
        } else {
            $fsStatus = 'mixed';
        }

        /*
         * Determine the overall Headquarters status.
         */
        if ($hqActiveCount === 0) {
            $hqStatus = 'inactive';
        } elseif ($hqInactiveCount === 0) {
            $hqStatus = 'active';
        } else {
            $hqStatus = 'mixed';
        }

        /*
         * Overall product status.
         */
        if (
            $fsStatus === 'active'
            && $hqStatus === 'active'
        ) {
            $status = 'both_active';
        } elseif (
            $fsStatus === 'active'
            && $hqStatus === 'inactive'
        ) {
            $status = 'fs_only';
        } elseif (
            $fsStatus === 'inactive'
            && $hqStatus === 'active'
        ) {
            $status = 'hq_only';
        } elseif (
            $fsStatus === 'inactive'
            && $hqStatus === 'inactive'
        ) {
            $status = 'both_inactive';
        } else {
            $status = 'mixed';
        }

        /*
         * If Shopify has more than 250 variants for this product,
         * the listing query only loads the first 250.
         *
         * Actions themselves use getAllProductVariants(), so
         * product-level activation/deactivation still processes
         * every variant.
         */
        $statusIsPartial = count($variants) < $variantCount;

        return [
            'id' => $product['id'] ?? null,

            'title' => $product['title'] ?? '',

            'handle' => $product['handle'] ?? null,

            'vendor' => $product['vendor'] ?? null,

            'image' => $product['featuredImage']['url'] ?? null,

            'image_alt' =>
                $product['featuredImage']['altText'] ?? null,

            'product_url' => $this->buildAdminProductUrl(
                $product['id'] ?? null
            ),

            'variant_count' => $variantCount,

            'variants_loaded' => count($variants),

            'status_is_partial' => $statusIsPartial,

            'fs_status' => $fsStatus,

            'fs_quantity' => $fsQuantity,

            'hq_status' => $hqStatus,

            'hq_quantity' => $hqQuantity,

            'status' => $status,

            'variants' => $variants,
        ];
    }

    /**
     * Get all variants for one product.
     *
     * This is used only when an action is performed.
     *
     * Shopify supports pagination on the product variants
     * connection, so this method continues until all variants
     * have been loaded.
     */
    protected function getAllProductVariants(
        ShopifyToken $shopifyToken,
        string $productId
    ): array {
        $variants = [];

        $after = null;

        do {
            $query = <<<'GRAPHQL'
query ProductVariants(
    $id: ID!
    $first: Int!
    $after: String
) {
    product(id: $id) {
        id
        title

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

            $response = $this->execute(
                $shopifyToken,
                $query,
                [
                    'id' => $productId,

                    'first' => self::VARIANTS_PER_PAGE,

                    'after' => $after,
                ]
            );

            $product = $response['product'] ?? null;

            if (! $product) {
                throw new RuntimeException(
                    "Shopify product was not found: {$productId}"
                );
            }

            $connection = $product['variants'] ?? [];

            foreach (
                ($connection['nodes'] ?? [])
                as $variant
            ) {
                $inventoryItem =
                    $variant['inventoryItem'] ?? null;

                if (! $inventoryItem) {
                    continue;
                }

                $inventoryItemId =
                    $inventoryItem['id'] ?? null;

                if (! $inventoryItemId) {
                    continue;
                }

                $variants[] = [
                    'id' =>
                        $variant['id'] ?? null,

                    'title' =>
                        $variant['title'] ?? '',

                    'sku' =>
                        $variant['sku'] ?? '',

                    'inventory_item_id' =>
                        $inventoryItemId,
                ];
            }

            $pageInfo = $connection['pageInfo'] ?? [];

            $hasNextPage = (bool) (
                $pageInfo['hasNextPage'] ?? false
            );

            $after = $pageInfo['endCursor'] ?? null;

        } while ($hasNextPage && $after !== null);

        return $variants;
    }

    /**
     * Perform a product-level inventory activation/deactivation action.
     *
     * IMPORTANT:
     * This method NEVER changes inventory quantities.
     *
     * It only changes whether each inventory item is active
     * at FSWarehouse and/or Headquarters.
     */
    public function productAction(
        string $vendor,
        string $productId,
        string $action
    ): array {
        $vendor = trim($vendor);
        $productId = trim($productId);
        $action = trim($action);

        if ($vendor === '') {
            throw new RuntimeException(
                'Vendor name is required.'
            );
        }

        if ($productId === '') {
            throw new RuntimeException(
                'Product ID is required.'
            );
        }

        if (! in_array($action, self::ACTIONS, true)) {
            throw new RuntimeException(
                'Invalid inventory action.'
            );
        }

        $shopifyToken = $this->getShopifyToken();

        $fsWarehouse = $this->getFswWarehouseLocation(
            $shopifyToken
        );

        $headquarters = $this->getHeadquartersLocation(
            $shopifyToken
        );

        $updates = $this->buildActionUpdates(
            $action,
            $fsWarehouse['id'],
            $headquarters['id']
        );

        /*
         * Fetch every variant of the selected product.
         *
         * This is deliberately done only for the product being
         * changed. We do NOT load the whole vendor catalog.
         */
        $variants = $this->getAllProductVariants(
            $shopifyToken,
            $productId
        );

        if (count($variants) === 0) {
            throw new RuntimeException(
                'No inventory-enabled variants were found for this product.'
            );
        }

        $results = [];

        $successful = 0;
        $failed = 0;

        foreach ($variants as $variant) {
            $inventoryItemId =
                $variant['inventory_item_id'];

            try {
                $this->toggleInventoryLocations(
                    $shopifyToken,
                    $inventoryItemId,
                    $updates
                );

                $successful++;

                $results[] = [
                    'variant_id' =>
                        $variant['id'],

                    'variant_title' =>
                        $variant['title'],

                    'sku' =>
                        $variant['sku'],

                    'inventory_item_id' =>
                        $inventoryItemId,

                    'success' => true,

                    'message' =>
                        $this->actionMessage($action),
                ];

                Log::info(
                    'Vendor inventory location action completed.',
                    [
                        'vendor' => $vendor,

                        'product_id' => $productId,

                        'variant_id' =>
                            $variant['id'],

                        'sku' =>
                            $variant['sku'],

                        'inventory_item_id' =>
                            $inventoryItemId,

                        'action' => $action,

                        'updates' => $updates,
                    ]
                );
            } catch (Throwable $e) {
                $failed++;

                $results[] = [
                    'variant_id' =>
                        $variant['id'],

                    'variant_title' =>
                        $variant['title'],

                    'sku' =>
                        $variant['sku'],

                    'inventory_item_id' =>
                        $inventoryItemId,

                    'success' => false,

                    'message' =>
                        $e->getMessage(),
                ];

                Log::error(
                    'Vendor inventory location action failed.',
                    [
                        'vendor' => $vendor,

                        'product_id' => $productId,

                        'variant_id' =>
                            $variant['id'],

                        'sku' =>
                            $variant['sku'],

                        'inventory_item_id' =>
                            $inventoryItemId,

                        'action' => $action,

                        'exception' => $e,
                    ]
                );
            }
        }

        return [
            'success' => $failed === 0,

            'vendor' => $vendor,

            'product_id' => $productId,

            'action' => $action,

            'action_label' =>
                $this->actionMessage($action),

            'fswarehouse' => [
                'id' => $fsWarehouse['id'],
                'name' => $fsWarehouse['name'],
            ],

            'headquarters' => [
                'id' => $headquarters['id'],
                'name' => $headquarters['name'],
            ],

            'summary' => [
                'total_variants' => count($variants),

                'successful' => $successful,

                'failed' => $failed,
            ],

            'results' => $results,
        ];
    }

    /**
     * Build activation updates for an action.
     *
     * No quantities are included here.
     */
    protected function buildActionUpdates(
        string $action,
        string $fsLocationId,
        string $hqLocationId
    ): array {
        return match ($action) {
            'activate_fs' => [
                [
                    'locationId' => $fsLocationId,
                    'activate' => true,
                ],
            ],

            'activate_hq' => [
                [
                    'locationId' => $hqLocationId,
                    'activate' => true,
                ],
            ],

            'activate_both' => [
                [
                    'locationId' => $fsLocationId,
                    'activate' => true,
                ],
                [
                    'locationId' => $hqLocationId,
                    'activate' => true,
                ],
            ],

            'deactivate_fs' => [
                [
                    'locationId' => $fsLocationId,
                    'activate' => false,
                ],
            ],

            'deactivate_hq' => [
                [
                    'locationId' => $hqLocationId,
                    'activate' => false,
                ],
            ],

            'deactivate_both' => [
                [
                    'locationId' => $fsLocationId,
                    'activate' => false,
                ],
                [
                    'locationId' => $hqLocationId,
                    'activate' => false,
                ],
            ],

            default => throw new RuntimeException(
                "Unsupported inventory action: {$action}"
            ),
        };
    }

    /**
     * Activate/deactivate an inventory item at one or more locations.
     *
     * IMPORTANT:
     *
     * There is intentionally NO inventory quantity mutation here.
     *
     * Do NOT add inventorySetQuantities() or inventoryAdjustQuantities()
     * to this method.
     */
    protected function toggleInventoryLocations(
        ShopifyToken $shopifyToken,
        string $inventoryItemId,
        array $updates
    ): array {
        if ($inventoryItemId === '') {
            throw new RuntimeException(
                'Inventory item ID is required.'
            );
        }

        if (empty($updates)) {
            throw new RuntimeException(
                'At least one inventory location update is required.'
            );
        }

        $query = <<<'GRAPHQL'
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

            isActive

            location {
                id
                name
            }

            quantities(names: ["available"]) {
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

        $response = $this->execute(
            $shopifyToken,
            $query,
            [
                'inventoryItemId' =>
                    $inventoryItemId,

                'inventoryItemUpdates' =>
                    $updates,
            ]
        );

        $payload =
            $response['inventoryBulkToggleActivation']
            ?? null;

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Shopify did not return an inventory activation response.'
            );
        }

        $this->throwUserErrors(
            $payload['userErrors'] ?? [],
            'Shopify inventory activation failed'
        );

        return $payload;
    }

    /**
     * Get FSWarehouse.
     *
     * The location ID comes from:
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

        return $this->getLocation(
            $shopifyToken,
            $locationId,
            'FSWarehouse'
        );
    }

    /**
     * Find the Headquarters location.
     *
     * Supported names:
     *
     * - Headquarters
     * - HQ
     */
    protected function getHeadquartersLocation(
        ShopifyToken $shopifyToken
    ): array {
        $query = <<<'GRAPHQL'
query HeadquartersLocations {
    locations(
        first: 100
    ) {
        nodes {
            id
            name
            isActive
        }
    }
}
GRAPHQL;

        $response = $this->execute(
            $shopifyToken,
            $query
        );

        $locations =
            $response['locations']['nodes']
            ?? [];

        foreach ($locations as $location) {
            $normalizedName =
                $this->normalizeLocationName(
                    $location['name'] ?? ''
                );

            if (
                in_array(
                    $normalizedName,
                    [
                        'headquarters',
                        'hq',
                    ],
                    true
                )
            ) {
                return [
                    'id' =>
                        $location['id'],

                    'name' =>
                        $location['name'],

                    'isActive' =>
                        (bool) (
                            $location['isActive']
                            ?? true
                        ),
                ];
            }
        }

        throw new RuntimeException(
            'Headquarters location was not found in Shopify. ' .
            'Expected a location named "Headquarters" or "HQ".'
        );
    }

    /**
     * Get one Shopify location by ID.
     */
    protected function getLocation(
        ShopifyToken $shopifyToken,
        string $locationId,
        string $expectedName
    ): array {
        $query = <<<'GRAPHQL'
query GetLocation(
    $id: ID!
) {
    location(id: $id) {
        id
        name
        isActive
    }
}
GRAPHQL;

        $response = $this->execute(
            $shopifyToken,
            $query,
            [
                'id' => $locationId,
            ]
        );

        $location =
            $response['location']
            ?? null;

        if (! is_array($location)) {
            throw new RuntimeException(
                "{$expectedName} location was not found in Shopify."
            );
        }

        return [
            'id' =>
                $location['id'],

            'name' =>
                $location['name'],

            'isActive' =>
                (bool) (
                    $location['isActive']
                    ?? true
                ),
        ];
    }

    /**
     * Normalize a Shopify location name.
     */
    protected function normalizeLocationName(
        string $name
    ): string {
        $name = trim(
            mb_strtolower($name)
        );

        /*
         * Remove spaces, underscores, hyphens and
         * other non-alphanumeric characters.
         */
        return preg_replace(
            '/[^a-z0-9]+/',
            '',
            $name
        ) ?? '';
    }

    /**
     * Get available quantity from an inventory level.
     *
     * This is READ ONLY.
     *
     * The returned quantity is only displayed in the UI.
     */
    protected function getAvailableQuantity(
        ?array $inventoryLevel
    ): int {
        if (! is_array($inventoryLevel)) {
            return 0;
        }

        foreach (
            ($inventoryLevel['quantities'] ?? [])
            as $quantity
        ) {
            if (
                ($quantity['name'] ?? '')
                === 'available'
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
     * Build Shopify product search query for a vendor.
     */
    protected function buildVendorQuery(
        string $vendor
    ): string {
        /*
         * Shopify search syntax:
         *
         * vendor:"A.C. Grace"
         *
         * Escape quotes and backslashes so a vendor name
         * cannot break the search expression.
         */
        $vendor = str_replace(
            [
                '\\',
                '"',
            ],
            [
                '\\\\',
                '\\"',
            ],
            $vendor
        );

        return 'vendor:"' . $vendor . '"';
    }

    /**
     * Build Shopify Admin product URL.
     *
     * Example:
     * https://admin.shopify.com/store/store-name/products/123
     *
     * The shop domain may be:
     * example.myshopify.com
     */
    protected function buildAdminProductUrl(
        ?string $productId
    ): ?string {
        if (! $productId) {
            return null;
        }

        $shopifyToken = ShopifyToken::query()->first();

        if (
            ! $shopifyToken
            || blank($shopifyToken->shop_domain)
        ) {
            return null;
        }

        /*
         * Extract the numeric Shopify product ID.
         */
        $numericId = $this->extractNumericId(
            $productId
        );

        if (! $numericId) {
            return null;
        }

        $shopDomain =
            strtolower(
                trim(
                    $shopifyToken->shop_domain
                )
            );

        /*
         * Remove .myshopify.com if present.
         */
        $storeHandle = preg_replace(
            '/\.myshopify\.com$/i',
            '',
            $shopDomain
        );

        if (! $storeHandle) {
            return null;
        }

        return sprintf(
            'https://admin.shopify.com/store/%s/products/%s',
            $storeHandle,
            $numericId
        );
    }

    /**
     * Extract the numeric ID from a Shopify GID.
     */
    protected function extractNumericId(
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

        return null;
    }

    /**
     * Return a human-readable action message.
     */
    protected function actionMessage(
        string $action
    ): string {
        return match ($action) {
            'activate_fs' =>
                'FSWarehouse activated successfully.',

            'activate_hq' =>
                'Headquarters activated successfully.',

            'activate_both' =>
                'FSWarehouse and Headquarters activated successfully.',

            'deactivate_fs' =>
                'FSWarehouse deactivated successfully.',

            'deactivate_hq' =>
                'Headquarters deactivated successfully.',

            'deactivate_both' =>
                'FSWarehouse and Headquarters deactivated successfully.',

            default =>
                'Inventory location action completed.',
        };
    }

    /**
     * Throw a readable exception when Shopify returns user errors.
     */
    protected function throwUserErrors(
        array $errors,
        string $prefix
    ): void {
        if (empty($errors)) {
            return;
        }

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

            if (is_array($field) && ! empty($field)) {
                $parts[] =
                    'field: '
                    . implode('.', $field);
            }

            if (is_string($field) && $field !== '') {
                $parts[] =
                    'field: '
                    . $field;
            }

            if ($code) {
                $parts[] =
                    'code: '
                    . $code;
            }

            $parts[] =
                'message: '
                . $message;

            $messages[] =
                implode(', ', $parts);
        }

        throw new RuntimeException(
            $prefix
            . ': '
            . implode('; ', $messages)
        );
    }

    /**
     * Execute a Shopify GraphQL request using the
     * existing ShopifyGraphQLService.
     *
     * Your existing ShopifyGraphQLService already handles
     * the HTTP request and top-level GraphQL errors.
     */
    protected function execute(
        ShopifyToken $shopifyToken,
        string $query,
        array $variables = []
    ): array {
        $response =
            $this->shopify->executeWithCredentials(
                $shopifyToken->shop_domain,
                $shopifyToken->access_token,
                $query,
                $variables
            );

        if (! is_array($response)) {
            throw new RuntimeException(
                'Invalid response received from Shopify GraphQL API.'
            );
        }

        return $response;
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
                'FSWarehouse fulfillment location ID is missing.'
            );
        }

        return $token;
    }
}