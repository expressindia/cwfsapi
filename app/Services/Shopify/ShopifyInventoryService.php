<?php

namespace App\Services\Shopify;

use RuntimeException;

class ShopifyInventoryService
{
    public function __construct(
        protected ShopifyGraphQLService $graphql
    ) {
    }

    public function setQuantity(
        string $inventoryItemId,
        int $quantity
    ): array {

        $locationId =
            config(
                'shopify.inventory_location_id'
            );

        if (!$locationId) {
            throw new RuntimeException(
                'SHOPIFY_INVENTORY_LOCATION_ID is not configured.'
            );
        }

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

            changes {
                name
                delta
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
            $this->graphql->execute(
                $mutation,
                [
                    'input' => [

                        'name' =>
                            'available',

                        'reason' =>
                            'correction',

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

        $result =
            $data[
                'inventorySetQuantities'
            ] ?? null;

        if (!$result) {
            throw new RuntimeException(
                'Shopify inventory update failed.'
            );
        }

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

        return $result;
    }
}