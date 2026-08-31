<?php

namespace App\Services\Shopify;

use RuntimeException;

class ShopifyProductService
{
    public function __construct(
        protected ShopifyGraphQLService $graphql
    ) {
    }

    public function getProduct(
        string $productId
    ): ?array {

        $query = <<<'GRAPHQL'
query GetProduct($id: ID!) {
    product(id: $id) {
        id
        title
        handle
        status
        vendor
        productType
        descriptionHtml

        variants(first: 250) {
            nodes {
                id
                title
                sku
                barcode
                price
                compareAtPrice

                inventoryItem {
                    id
                    sku
                }
            }
        }
    }
}
GRAPHQL;

        $data = $this->graphql->execute(
            $query,
            [
                'id' => $productId,
            ]
        );

        return $data['product'] ?? null;
    }

    public function findProductBySku(
        string $sku
    ): ?array {

        $query = <<<'GRAPHQL'
query SearchVariants($query: String!) {
    productVariants(
        first: 10,
        query: $query
    ) {
        nodes {
            id
            sku

            product {
                id
                title
                handle
                status
            }

            inventoryItem {
                id
                sku
            }
        }
    }
}
GRAPHQL;

        $data = $this->graphql->execute(
            $query,
            [
                'query' =>
                    'sku:"' . addslashes($sku) . '"',
            ]
        );

        $variants =
            $data[
                'productVariants'
            ]['nodes'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Exact SKU match only
        |--------------------------------------------------------------------------
        */

        foreach ($variants as $variant) {

            if (
                trim(
                    (string) (
                        $variant['sku'] ?? ''
                    )
                ) === $sku
            ) {
                return $variant;
            }
        }

        return null;
    }

    public function createOrUpdate(
        array $product,
        ?string $shopifyProductId = null
    ): array {

        $input = [
            'title' =>
                $product['title'],

            'descriptionHtml' =>
                $product['description_html'] ?? '',

            'vendor' =>
                $product['vendor'] ?? 'Fullscript',

            'productType' =>
                $product['product_type']
                ?? 'Supplement',

            'status' =>
                $product['status'] ?? 'ACTIVE',
        ];

        if (
            !empty($product['handle'])
        ) {
            $input['handle'] =
                $product['handle'];
        }

        if ($shopifyProductId) {

            $input['id'] =
                $shopifyProductId;
        }

        if (!empty($product['variants'])) {

            $input['variants'] = [];

            foreach (
                $product['variants']
                as $variant
            ) {

                $variantInput = [
                    'sku' =>
                        $variant['sku'],

                    'price' =>
                        (string) (
                            $variant['price']
                            ?? '0.00'
                        ),
                ];

                if (
                    !empty(
                        $variant['barcode']
                    )
                ) {
                    $variantInput['barcode'] =
                        $variant['barcode'];
                }

                if (
                    $variant['compare_at_price']
                    !== null
                ) {
                    $variantInput[
                        'compareAtPrice'
                    ] =
                        $variant[
                            'compare_at_price'
                        ];
                }

                $input['variants'][] =
                    $variantInput;
            }
        }

        $mutation = <<<'GRAPHQL'
mutation ProductSet(
    $input: ProductSetInput!,
    $identifier: ProductSetIdentifiers
) {
    productSet(
        input: $input,
        identifier: $identifier,
        synchronous: true
    ) {
        product {
            id
            title
            handle
            status
            vendor
            productType
            descriptionHtml

            variants(first: 250) {
                nodes {
                    id
                    title
                    sku
                    barcode
                    price
                    compareAtPrice

                    inventoryItem {
                        id
                        sku
                    }
                }
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

        $identifier = null;

        if ($shopifyProductId) {
            $identifier = [
                'id' =>
                    $shopifyProductId,
            ];
        }

        $data = $this->graphql->execute(
            $mutation,
            [
                'input' =>
                    $input,

                'identifier' =>
                    $identifier,
            ]
        );

        $result =
            $data['productSet'] ?? null;

        if (!$result) {
            throw new RuntimeException(
                'Shopify productSet returned no result.'
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

        if (
            empty($result['product'])
        ) {
            throw new RuntimeException(
                'Shopify productSet did not return product.'
            );
        }

        return $result['product'];
    }
}