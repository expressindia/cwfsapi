<?php

namespace App\Services\Shopify;

use RuntimeException;

class ShopifyProductService
{
    public function __construct( protected ShopifyGraphQLService $graphql ) {
    }

    /**
     * Get a Shopify product by ID.
     */
    public function getProduct( string $productId ): ?array {

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

    /**
     * Find Shopify product variant by SKU.
     */
    public function findProductBySku( string $sku ): ?array 
    {

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

        $data = $this->graphql->execute( $query, [ 'query' => 'sku:"' . addslashes($sku) . '"', ] );

        $variants = $data['productVariants']['nodes'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Exact SKU match only
        |--------------------------------------------------------------------------
        */

        foreach ($variants as $variant) {

            if ( trim( (string) ($variant['sku'] ?? '') ) === $sku ) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Create or update Shopify product.
     */
    public function createOrUpdate( array $product, ?string $shopifyProductId = null ): array {

        $input = [
            'title' => $product['title'],
            'descriptionHtml' => $product['description_html'] ?? '',
            'vendor' => $product['vendor'] ?? 'Fullscript',
            'productType' => $product['product_type'] ?? 'Supplement',
            'status' => $product['status'] ?? 'ACTIVE',
            'productOptions' => $product['product_options'] ?? [],
        ];

        /*
        |--------------------------------------------------------------------------
        | Handle
        |--------------------------------------------------------------------------
        */

        if (!empty($product['handle'])) { 
            $input['handle'] = $product['handle'];
        }

        /*
        |--------------------------------------------------------------------------
        | Product Images
        |--------------------------------------------------------------------------
        */
        
        // $files = $this->buildProductFiles($product);

        // if (!empty($files)) {
        //     $input['files'] = $files;
        // }

        
        if (!empty($product['images'])) {
            $input['files'] = [];

            foreach ($product['images'] as $image) {
                if (empty($image['url'])) {
                    continue;
                }

                $input['files'][] = [
                    'originalSource' => $image['url'],
                    'alt' => $image['alt'] ?? $product['title'],
                    'contentType' => 'IMAGE',
                ];
            }
        }
        /*
        |--------------------------------------------------------------------------
        | Existing Shopify product
        |--------------------------------------------------------------------------
        */

        // if ($shopifyProductId) { 
        //     $input['id'] = $shopifyProductId;
        // }

        
        /*
        |--------------------------------------------------------------------------
        | Variants
        |--------------------------------------------------------------------------
        */

        if (!empty($product['variants'])) {

            $input['variants'] = [];

            foreach ( $product['variants'] as $variant ) {

                $variantInput = [ 'sku' => $variant['sku'],

                    'price' => (string) (  $variant['price'] ?? '0.00' ),

                    /*
                    |--------------------------------------------------------------------------
                    | Shopify requires optionValues
                    |--------------------------------------------------------------------------
                    */

                    'optionValues' => [
                        [
                            'optionName' => $variant['option_name'],
                            'name' => $variant['option_value'],
                        ],
                    ],
                ];

                /*
                |--------------------------------------------------------------------------
                | Barcode
                |--------------------------------------------------------------------------
                */

                if ( !empty( $variant['barcode'] ) ) {
                    $variantInput['barcode'] = $variant['barcode'];
                }

                /*
                |--------------------------------------------------------------------------
                | Compare at price
                |--------------------------------------------------------------------------
                |
                | Fullscript currently does not provide
                | compare_at_price, so only send it when
                | the transformed data actually contains it.
                |
                */

                if ( isset( $variant['compare_at_price'] ) && $variant['compare_at_price'] !== null ) {
                    $variantInput['compareAtPrice'] =  $variant['compare_at_price'];
                }

                $input['variants'][] = $variantInput;
            }
        }


        
        /*
        |--------------------------------------------------------------------------
        | Shopify GraphQL mutation
        |--------------------------------------------------------------------------
        */

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

            media(first: 10) {
                nodes {
                    id
                    alt
                    mediaContentType
                    status
                }
            }
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


        /*
        |--------------------------------------------------------------------------
        | Identifier
        |--------------------------------------------------------------------------
        */

        $identifier = null;

        if ($shopifyProductId) {
            $identifier = [
                'id' => $shopifyProductId,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Shopify mutation
        |--------------------------------------------------------------------------
        */

        $data = $this->graphql->execute(
            $mutation,
            [
                'input' => $input,
                'identifier' => $identifier,
            ]
        );

        $result = $data['productSet'] ?? null;

        if (!$result) {
            throw new RuntimeException(
                'Shopify productSet returned no result.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shopify user errors
        |--------------------------------------------------------------------------
        */

        if ( !empty( $result['userErrors'] ) ) {
            throw new RuntimeException( json_encode( $result['userErrors'], JSON_PRETTY_PRINT  ) );
        }

        /*
        |--------------------------------------------------------------------------
        | Product result
        |--------------------------------------------------------------------------
        */

        if ( empty( $result['product'] ) ) {
            throw new RuntimeException(
                'Shopify productSet did not return product.'
            );
        }

        return $result['product'];
    }
}