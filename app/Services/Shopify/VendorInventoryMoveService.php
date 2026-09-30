<?php

namespace App\Services\Shopify;

use Illuminate\Support\Str;
use RuntimeException;

class VendorInventoryMoveService
{
    public function __construct(
        protected ShopifyGraphQLService $graphql
    ) {
    }

    /**
     * Preview a vendor inventory move.
     *
     * No Shopify data is modified.
     */
    public function preview(string $vendor): array
    {
        $vendor = trim($vendor);

        if ($vendor === '') {
            throw new RuntimeException('Vendor name is required.');
        }

        $fsWarehouse = $this->findFsWarehouse();

        if (!$fsWarehouse) {
            throw new RuntimeException(
                'FSWarehouse location was not found.'
            );
        }

        $variants = $this->getVendorVariants($vendor);

        $rows = [];

        foreach ($variants as $variant) {
            $levels = $variant['inventoryItem']['inventoryLevels']['nodes'] ?? [];

            $fsQuantity = 0;
            $otherQuantity = 0;
            $otherLocations = [];

            foreach ($levels as $level) {
                $locationId = $level['location']['id'] ?? null;
                $locationName = $level['location']['name'] ?? '-';

                $quantity = $this->availableQuantity($level);

                if ($locationId === $fsWarehouse['id']) {
                    $fsQuantity = $quantity;

                    continue;
                }

                if ($quantity > 0) {
                    $otherQuantity += $quantity;
                }

                $otherLocations[] = [
                    'id' => $locationId,
                    'name' => $locationName,
                    'quantity' => $quantity,
                    'is_active' => (bool) ($level['isActive'] ?? false),
                    'can_deactivate' => (bool) ($level['canDeactivate'] ?? false),
                    'inventory_level_id' => $level['id'] ?? null,
                ];
            }

            $rows[] = [
                'product_id' => $variant['product']['id'],
                'product_title' => $variant['product']['title'],
                'vendor' => $variant['product']['vendor'],
                'variant_id' => $variant['id'],
                'variant_title' => $variant['title'],
                'sku' => $variant['sku'],
                'inventory_item_id' => $variant['inventoryItem']['id'],
                'fs_quantity' => $fsQuantity,
                'other_quantity' => $otherQuantity,
                'new_fs_quantity' => $fsQuantity + $otherQuantity,
                'other_locations' => $otherLocations,
            ];
        }

        return [
            'vendor' => $vendor,
            'fs_warehouse' => $fsWarehouse,
            'variants' => $rows,
            'variant_count' => count($rows),
            'inventory_to_move' => array_sum(
                array_column($rows, 'other_quantity')
            ),
            'new_fs_inventory' => array_sum(
                array_column($rows, 'new_fs_quantity')
            ),
        ];
    }

    /**
     * Move all inventory for a vendor to FSWarehouse.
     *
     * This method modifies Shopify.
     */
    public function move(string $vendor): array
    {
        $preview = $this->preview($vendor);

        $moved = [];
        $errors = [];

        foreach ($preview['variants'] as $variant) {
            try {
                $this->moveVariant(
                    $variant,
                    $preview['fs_warehouse']['id']
                );

                $moved[] = [
                    'sku' => $variant['sku'],
                    'product' => $variant['product_title'],
                    'variant' => $variant['variant_title'],
                    'old_fs_quantity' => $variant['fs_quantity'],
                    'moved_quantity' => $variant['other_quantity'],
                    'new_fs_quantity' => $variant['new_fs_quantity'],
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'sku' => $variant['sku'],
                    'product' => $variant['product_title'],
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'vendor' => $vendor,
            'fs_warehouse' => $preview['fs_warehouse'],
            'moved' => $moved,
            'errors' => $errors,
            'moved_count' => count($moved),
            'error_count' => count($errors),
        ];
    }

    /**
     * Move one variant.
     */
    protected function moveVariant(
        array $variant,
        string $fsWarehouseId
    ): void {
        $inventoryItemId = $variant['inventory_item_id'];

        /*
         * ----------------------------------------------------------------------
         * 1. Make sure FSWarehouse is active.
         * ----------------------------------------------------------------------
         */

        $fsLevel = null;

        foreach ($variant['other_locations'] as $location) {
            // Other locations only; FSWarehouse is handled below.
        }

        /*
         * The preview query contains active/inactive inventory levels.
         * If FSWarehouse isn't currently present, activate it.
         */
        if ($variant['fs_quantity'] === null) {
            $this->activateInventory(
                $inventoryItemId,
                $fsWarehouseId
            );
        }

        /*
         * ----------------------------------------------------------------------
         * 2. Set FSWarehouse to the combined quantity.
         * ----------------------------------------------------------------------
         *
         * We do this before deactivating the old locations so inventory isn't
         * temporarily lost.
         */

        $this->setInventoryQuantity(
            $inventoryItemId,
            $fsWarehouseId,
            $variant['new_fs_quantity']
        );

        /*
         * ----------------------------------------------------------------------
         * 3. Remove/deactivate all other locations.
         * ----------------------------------------------------------------------
         */

        foreach ($variant['other_locations'] as $location) {
            if (!$location['is_active']) {
                continue;
            }

            if (!$location['can_deactivate']) {
                throw new RuntimeException(
                    sprintf(
                        'Cannot deactivate location "%s" for SKU "%s".',
                        $location['name'],
                        $variant['sku'] ?: '-'
                    )
                );
            }

            if (empty($location['inventory_level_id'])) {
                continue;
            }

            $this->deactivateInventory(
                $location['inventory_level_id']
            );
        }
    }

    /**
     * Find FSWarehouse dynamically.
     */
    protected function findFsWarehouse(): ?array
    {
        $query = <<<'GRAPHQL'
query GetLocations {
    locations(first: 250) {
        nodes {
            id
            name
            isActive
        }
    }
}
GRAPHQL;

        $data = $this->graphql->execute($query);

        foreach ($data['locations']['nodes'] ?? [] as $location) {
            if (
                strcasecmp(
                    trim($location['name'] ?? ''),
                    'FSWarehouse'
                ) === 0
            ) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Get all variants for a vendor.
     */
    protected function getVendorVariants(string $vendor): array
    {
        $variants = [];
        $after = null;

        do {
            $query = <<<'GRAPHQL'
query GetVendorProducts(
    $query: String!
    $after: String
) {
    products(
        first: 250
        after: $after
        query: $query
        sortKey: VENDOR
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

                        inventoryLevels(
                            first: 250
                            includeInactive: true
                        ) {
                            nodes {
                                id
                                isActive
                                canDeactivate

                                location {
                                    id
                                    name
                                }

                                quantities(names: ["available"]) {
                                    name
                                    quantity
                                }
                            }
                        }
                    }

                    product {
                        id
                        title
                        vendor
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
            endCursor
        }
    }
}
GRAPHQL;

            $data = $this->graphql->execute(
                $query,
                [
                    'query' => 'vendor:"' . $vendor . '"',
                    'after' => $after,
                ]
            );

            $products = $data['products'] ?? [];

            foreach ($products['nodes'] ?? [] as $product) {
                foreach (
                    $product['variants']['nodes'] ?? []
                    as $variant
                ) {
                    if (blank($variant['inventoryItem']['id'] ?? null)) {
                        continue;
                    }

                    $variant['product'] = [
                        'id' => $product['id'],
                        'title' => $product['title'],
                        'vendor' => $product['vendor'],
                    ];

                    $variants[] = $variant;
                }

                /*
                 * If a product has more than 250 variants, we should handle
                 * variant pagination separately. Most normal products will
                 * not reach this limit.
                 */
            }

            $pageInfo = $products['pageInfo'] ?? [];

            $after = $pageInfo['hasNextPage']
                ? ($pageInfo['endCursor'] ?? null)
                : null;

        } while ($after !== null);

        return $variants;
    }

    /**
     * Read available quantity.
     */
    protected function availableQuantity(array $level): int
    {
        foreach ($level['quantities'] ?? [] as $quantity) {
            if (($quantity['name'] ?? null) === 'available') {
                return (int) ($quantity['quantity'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Activate an inventory item at FSWarehouse.
     */
    protected function activateInventory(
        string $inventoryItemId,
        string $locationId
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventoryActivate(
    $inventoryItemId: ID!
    $locationId: ID!
    $idempotencyKey: String!
) {
    inventoryActivate(
        inventoryItemId: $inventoryItemId
        locationId: $locationId
    ) @idempotent(key: $idempotencyKey) {
        inventoryLevel {
            id
        }

        userErrors {
            field
            message
        }
    }
}
GRAPHQL;

        $data = $this->graphql->execute(
            $mutation,
            [
                'inventoryItemId' => $inventoryItemId,
                'locationId' => $locationId,
                'idempotencyKey' => (string) Str::uuid(),
            ]
        );

        $errors = $data['inventoryActivate']['userErrors'] ?? [];

        if (!empty($errors)) {
            throw new RuntimeException(
                $this->formatUserErrors($errors)
            );
        }
    }

    /**
     * Set available inventory quantity.
     */
    protected function setInventoryQuantity(
        string $inventoryItemId,
        string $locationId,
        int $quantity
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventorySet(
    $input: InventorySetQuantitiesInput!
    $idempotencyKey: String!
) {
    inventorySetQuantities(
        input: $input
    ) @idempotent(key: $idempotencyKey) {
        inventoryAdjustmentGroup {
            changes {
                name
                delta
                quantityAfterChange
            }
        }

        userErrors {
            field
            message
        }
    }
}
GRAPHQL;

        $data = $this->graphql->execute(
            $mutation,
            [
                'input' => [
                    'name' => 'available',
                    'reason' => 'correction',
                    'referenceDocumentUri' =>
                        'cwfsapi://vendor-inventory-move/' .
                        Str::uuid(),

                    'quantities' => [
                        [
                            'inventoryItemId' => $inventoryItemId,
                            'locationId' => $locationId,
                            'quantity' => $quantity,
                        ],
                    ],
                ],

                'idempotencyKey' => (string) Str::uuid(),
            ]
        );

        $errors = $data['inventorySetQuantities']['userErrors'] ?? [];

        if (!empty($errors)) {
            throw new RuntimeException(
                $this->formatUserErrors($errors)
            );
        }
    }

    /**
     * Deactivate an inventory level.
     */
    protected function deactivateInventory(
        string $inventoryLevelId
    ): void {
        $mutation = <<<'GRAPHQL'
mutation InventoryDeactivate(
    $inventoryLevelId: ID!
    $idempotencyKey: String!
) {
    inventoryDeactivate(
        inventoryLevelId: $inventoryLevelId
    ) @idempotent(key: $idempotencyKey) {
        userErrors {
            field
            message
        }
    }
}
GRAPHQL;

        $data = $this->graphql->execute(
            $mutation,
            [
                'inventoryLevelId' => $inventoryLevelId,
                'idempotencyKey' => (string) Str::uuid(),
            ]
        );

        $errors =
            $data['inventoryDeactivate']['userErrors'] ?? [];

        if (!empty($errors)) {
            throw new RuntimeException(
                $this->formatUserErrors($errors)
            );
        }
    }

    /**
     * Format Shopify user errors.
     */
    protected function formatUserErrors(array $errors): string
    {
        return collect($errors)
            ->map(function ($error) {
                $field = !empty($error['field'])
                    ? implode('.', (array) $error['field']) . ': '
                    : '';

                return $field . ($error['message'] ?? 'Unknown Shopify error.');
            })
            ->implode('; ');
    }
}