<?php







namespace App\Services\Shopify;







use App\Models\ShopifyToken;



use RuntimeException;







class ShopifyProductService



{



    public function __construct(



        protected ShopifyGraphQLService $graphql



    ) {



    }







    /**



     * Get a Shopify product by ID.



     */



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



        isGiftCard



        tags







        options {



            id



            name



            position



            values



            optionValues {



                id



                name



            }



        }







        seo {



            title



            description



        }







        variants(first: 250) {



            nodes {



                id



                title



                sku







                selectedOptions {



                    name



                    value



                    optionValue {



                        id



                        name



                    }



                }







                barcode



                price



                compareAtPrice



                taxable



                inventoryPolicy







                inventoryItem {



                    id



                    sku



                    tracked



                    requiresShipping







                    unitCost {



                        amount



                        currencyCode



                    }







                    inventoryLevels(



                        first: 50



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



}



GRAPHQL;







        $data =



            $this->graphql->execute(



                $query,



                [



                    'id' =>



                        $productId,



                ]



            );







        return $data['product']



            ?? null;



    }







    /**



     * Find Shopify product variant by SKU.



     */



    public function findProductBySku(



        string $sku



    ): ?array {







        $query = <<<'GRAPHQL'



query SearchVariants($query: String!) {



    productVariants(



        first: 10



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
                updatedAt



            }







            inventoryItem {



                id



                sku



            }



        }



    }



}



GRAPHQL;







        $data =



            $this->graphql->execute(



                $query,



                [



                    'query' =>



                        'sku:"'



                        . addslashes($sku)



                        . '"',



                ]



            );







        $variants =



            $data[



                'productVariants'



            ]['nodes']



            ?? [];







        foreach ($variants as $variant) {







            if (



                trim(



                    (string) (



                        $variant['sku']



                        ?? ''



                    )



                )



                === $sku



            ) {



                return $variant;



            }



        }







        return null;



    }







    /**



     * Find Shopify product variants for multiple SKUs.



     *



     * This method is used by the product listing page so Shopify is not



     * queried once for every Fullscript product. The search query is built



     * from the requested SKUs, and the returned variants are still matched



     * by exact SKU in PHP before they are returned to the controller.



     */



    public function findProductsBySkus(



        array $skus



    ): array {



        $skus =



            collect($skus)



                ->map(

                    fn ($sku) =>

                        trim((string) $sku)

                )



                ->filter(

                    fn ($sku) =>

                        $sku !== ''

                )



                ->unique()



                ->values()



                ->all();



        if (empty($skus)) {



            return [];



        }



        $query = <<<'GRAPHQL'

query SearchVariants($query: String!) {
    productVariants(
        first: 250
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
                updatedAt
            }

            inventoryItem {
                id
                sku
            }
        }
    }
}
GRAPHQL;



        $matches = [];



        /*



        |--------------------------------------------------------------------------



        | Shopify search query size



        |--------------------------------------------------------------------------



        |



        | Keep the number of SKU clauses per request small. A product listing



        | normally contains 25 products, but this also works with the 50/100



        | page sizes without creating an unnecessarily large search query.



        */



        foreach (array_chunk($skus, 25) as $skuChunk) {



            $clauses =



                array_map(

                    fn ($sku) =>

                        'sku:"'

                        . addslashes($sku)

                        . '"',

                    $skuChunk

                );



            $data =



                $this->graphql->execute(

                    $query,

                    [

                        'query' =>

                            implode(

                                ' OR ',

                                $clauses

                            ),

                    ]

                );



            $variants =



                $data[

                    'productVariants'

                ]['nodes']

                ?? [];



            foreach ($variants as $variant) {



                $variantSku =

                    trim(

                        (string) (

                            $variant['sku']

                            ?? ''

                        )

                    );



                if (

                    $variantSku === ''

                    || !in_array(

                        $variantSku,

                        $skuChunk,

                        true

                    )

                ) {

                    continue;

                }



                /*



                |--------------------------------------------------------------------------



                | Exact SKU match



                |--------------------------------------------------------------------------



                |



                | Shopify search can return a broader match. Do not treat a



                | partial or related SKU as an existing product.



                |



                */



                $matches[$variantSku] =

                    $variant;

            }

        }



        return $matches;

    }



    /**



     * Find Shopify product by handle.



     */



    public function findProductByHandle(



        string $handle



    ): ?array {







        $query = <<<'GRAPHQL'



query SearchProductByHandle($query: String!) {



    products(



        first: 10



        query: $query



    ) {



        nodes {



            id



            title



            handle



            status



            vendor



            productType



        }



    }



}



GRAPHQL;







        $data =



            $this->graphql->execute(



                $query,



                [



                    'query' =>



                        'handle:"'



                        . addslashes($handle)



                        . '"',



                ]



            );







        $products =



            $data[



                'products'



            ]['nodes']



            ?? [];







        foreach ($products as $product) {







            if (



                trim(



                    (string) (



                        $product['handle']



                        ?? ''



                    )



                )



                === $handle



            ) {







                return [



                    'product' =>



                        $product,



                ];



            }



        }







        return null;



    }







    /**



     * Create or update Shopify product.



     */



    public function createOrUpdate(



        array $product,



        ?string $shopifyProductId = null



    ): array {







        /*



        |--------------------------------------------------------------------------



        | Get FSWarehouse



        |--------------------------------------------------------------------------



        */







        $fsWarehouseLocationId = $this->getFsWarehouseLocationId();







        /*



        |--------------------------------------------------------------------------



        | Automatically resolve existing Shopify product by SKU



        |--------------------------------------------------------------------------



        |



        | If the controller did not provide a Shopify product ID, check Shopify



        | using the Fullscript SKU before creating a new product.



        |



        | This prevents duplicate Shopify products when the Update button is



        | clicked without a Shopify product ID.



        |



        */







        if (!$shopifyProductId) {







            foreach (



                $product['variants'] ?? []



                as $variant



            ) {







                $sku = trim(



                    (string) (



                        $variant['sku']



                        ?? ''



                    )



                );







                if ($sku === '') {



                    continue;



                }







                $existingVariant =



                    $this->findProductBySku($sku);







                if (!$existingVariant) {



                    continue;



                }







                $foundProductId =



                    $existingVariant['product']['id']



                    ?? null;







                if ($foundProductId) {







                    $shopifyProductId =



                        $foundProductId;







                    break;



                }



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Get existing Shopify product first



        |--------------------------------------------------------------------------



        */







        $existingProduct = null;







        if ($shopifyProductId) {







            $existingProduct =



                $this->getProduct(



                    $shopifyProductId



                );







            if (!$existingProduct) {







                throw new RuntimeException(



                    "Shopify product {$shopifyProductId} was not found."



                );



            }



        }







        /*



        |--------------------------------------------------------------------------



        | ProductSet input



        |--------------------------------------------------------------------------



        */







        $input = [



            'title' =>



                $product['title'],







            'descriptionHtml' =>



                $product['description_html']



                ?? '',







            'vendor' =>



                $product['vendor']



                ?? 'Fullscript',







            'productType' =>



                $product['product_type']



                ?? 'Vitamins & Supplements',







            'status' =>



                $product['status']



                ?? 'ACTIVE',







            'giftCard' =>



                (bool) (



                    $product['gift_card']



                    ?? false



                ),







            'tags' =>



                $product['tags']



                ?? [],







            /*



            |--------------------------------------------------------------------------



            | Product options



            |--------------------------------------------------------------------------



            |



            | Shopify requires productOptions when productSet updates variants.



            | Existing products use their current Shopify options as the source



            | of truth. New products use the Fullscript/controller data.



            |



            */



            'productOptions' =>



                $this->buildProductOptions(



                    $product,



                    $existingProduct



                ),



        ];







        /*



        |--------------------------------------------------------------------------



        | Category



        |--------------------------------------------------------------------------



        |



        | Intentionally omitted.



        |



        | Shopify Category remains blank.



        |



        */







        /*



        |--------------------------------------------------------------------------



        | SEO



        |--------------------------------------------------------------------------



        */







        if (



            !empty(



                $product['seo']



            )



        ) {







            $input['seo'] = [



                'title' =>



                    $product['seo']['title']



                    ?? null,







                'description' =>



                    $product['seo']['description']



                    ?? null,



            ];



        }







        /*



        |--------------------------------------------------------------------------



        | Handle



        |--------------------------------------------------------------------------



        |



        | Existing product:



        | preserve its existing Shopify handle.



        |



        | New product:



        | use the generated Fullscript handle.



        |



        */







        if ($existingProduct) {







            if (



                !empty(



                    $existingProduct['handle']



                )



            ) {







                $input['handle'] =



                    $existingProduct['handle'];



            }







        } elseif (



            !empty(



                $product['handle']



            )



        ) {







            $input['handle'] =



                $product['handle'];



        }







        /*



        |--------------------------------------------------------------------------



        | Images



        |--------------------------------------------------------------------------



        */







        if (



            !empty(



                $product['images']



            )



        ) {







            $input['files'] = [];







            foreach (



                $product['images']



                as $image



            ) {







                if (



                    empty(



                        $image['url']



                    )



                ) {



                    continue;



                }







                $input['files'][] = [



                    'originalSource' =>



                        $image['url'],







                    'alt' =>



                        $image['alt']



                        ?? $product['title'],







                    'contentType' =>



                        'IMAGE',



                ];



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Existing variants indexed by SKU



        |--------------------------------------------------------------------------



        */







        $existingVariantsBySku = [];







        if ($existingProduct) {







            foreach (



                $existingProduct[



                    'variants'



                ]['nodes'] ?? []



                as $existingVariant



            ) {







                $existingSku =



                    trim(



                        (string) (



                            $existingVariant[



                                'sku'



                            ] ?? ''



                        )



                    );







                if ($existingSku === '') {



                    continue;



                }







                $existingVariantsBySku[



                    $existingSku



                ] =



                    $existingVariant;



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Activate FSWarehouse for existing variants



        |--------------------------------------------------------------------------



        */







        if ($existingProduct) {







            foreach (



                $product['variants']



                ?? []



                as $variant



            ) {







                $sku =



                    trim(



                        (string) (



                            $variant['sku']



                            ?? ''



                        )



                    );







                if (



                    $sku === ''



                    ||



                    !isset(



                        $existingVariantsBySku[



                            $sku



                        ]



                    )



                ) {



                    continue;



                }







                $existingVariant =



                    $existingVariantsBySku[



                        $sku



                    ];







                $inventoryItemId =



                    $existingVariant[



                        'inventoryItem'



                    ]['id']



                    ?? null;







                if (!$inventoryItemId) {



                    continue;



                }







                $this->activateInventoryLocation(



                    $inventoryItemId,



                    $fsWarehouseLocationId



                );



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Build variants



        |--------------------------------------------------------------------------



        */







        if (



            !empty(



                $product['variants']



            )



        ) {







            $input['variants'] = [];







            foreach (



                $product['variants']



                as $variant



            ) {







                $sku =



                    trim(



                        (string) (



                            $variant['sku']



                            ?? ''



                        )



                    );







                $variantInput = [







                    'sku' =>



                        $sku,







                    'price' =>



                        (string) (



                            $variant['price']



                            ?? '0.00'



                        ),







                    'inventoryPolicy' =>



                        'DENY',







                    'taxable' =>



                        true,







                    'inventoryItem' => [



                        'tracked' =>



                            true,







                        'requiresShipping' =>



                            true,



                    ],







                    'optionValues' =>



                        $this->buildVariantOptionValues(



                            $variant,



                            $existingVariantsBySku[



                                $sku



                            ] ?? null,



                            $input['productOptions']



                        ),



                ];







                /*



                |--------------------------------------------------------------------------



                | Existing Shopify variant ID



                |--------------------------------------------------------------------------



                */







                if (



                    isset(



                        $existingVariantsBySku[



                            $sku



                        ]



                    )



                ) {







                    $variantInput['id'] =



                        $existingVariantsBySku[



                            $sku



                        ]['id'];



                }







                /*



                |--------------------------------------------------------------------------



                | Barcode



                |--------------------------------------------------------------------------



                */







                if (



                    !empty(



                        $variant['barcode']



                    )



                ) {







                    $variantInput[



                        'barcode'



                    ] =



                        (string) (



                            $variant['barcode']



                        );



                }







                /*



                |--------------------------------------------------------------------------



                | Compare-at price



                |--------------------------------------------------------------------------



                */







                if (



                    isset(



                        $variant[



                            'compare_at_price'



                        ]



                    )



                    &&



                    $variant[



                        'compare_at_price'



                    ] !== null



                ) {







                    $variantInput[



                        'compareAtPrice'



                    ] =



                        $variant[



                            'compare_at_price'



                        ];



                }







                /*



                |--------------------------------------------------------------------------



                | Cost



                |--------------------------------------------------------------------------



                */







                if (



                    isset(



                        $variant['cost']



                    )



                    &&



                    $variant['cost'] !== null



                    &&



                    $variant['cost'] !== ''



                ) {







                    $variantInput[



                        'inventoryItem'



                    ]['cost'] =



                        (string) (



                            $variant['cost']



                        );



                }







                /*



                |--------------------------------------------------------------------------



                | Weight



                |--------------------------------------------------------------------------



                |



                | Intentionally NOT sent.



                |



                | No measurement.



                | No weight.



                | No weight unit.



                |



                */







                /*



                |--------------------------------------------------------------------------



                | Inventory quantity



                |--------------------------------------------------------------------------



                */







                $variantInput[



                    'inventoryQuantities'



                ] = [



                    [



                        'locationId' =>



                            $fsWarehouseLocationId,







                        'name' =>



                            'available',







                        'quantity' =>



                            (int) config(



                                'fullscript.default_inventory',



                                88



                            ),



                    ],



                ];







                $input['variants'][] =



                    $variantInput;



            }



        }



        \Log::info(

    'SHOPIFY PRODUCT IMAGE INPUT',

    [

        'title' =>

            $product['title'] ?? null,



        'images' =>

            $product['images'] ?? [],



        'files' =>

            $input['files'] ?? [],

    ]

);



        /*



        |--------------------------------------------------------------------------



        | Shopify ProductSet mutation



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



            isGiftCard



            tags







            seo {



                title



                description



            }







            media(first: 10) {

                nodes {

                    id

                    alt

                    mediaContentType

                    status



                    ... on MediaImage {

                        image {

                            url

                            width

                            height

                        }

                    }

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



                    taxable



                    inventoryPolicy







                    inventoryItem {



                        id



                        sku



                        tracked



                        requiresShipping







                        unitCost {



                            amount



                            currencyCode



                        }



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



                'id' =>



                    $shopifyProductId,



            ];



        }







        /*



        |--------------------------------------------------------------------------



        | Execute Shopify mutation



        |--------------------------------------------------------------------------



        */







        $data =



            $this->graphql->execute(



                $mutation,



                [



                    'input' =>



                        $input,







                    'identifier' =>



                        $identifier,



                ]



            );







        $result =



            $data['productSet']



            ?? null;







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







        /*



        |--------------------------------------------------------------------------



        | Product result



        |--------------------------------------------------------------------------



        */







        if (



            empty(



                $result['product']



            )



        ) {







            throw new RuntimeException(



                'Shopify productSet did not return product.'



            );



        }







        $shopifyProduct =



            $result['product'];



        \Log::info('Shopify ProductSet Image Result', [

            'product_id' => $shopifyProduct['id'] ?? null,

            'title' => $shopifyProduct['title'] ?? null,

            'input_images' => $input['files'] ?? [],

            'shopify_media' => $shopifyProduct['media'] ?? [],

        ]);



        /*



        |--------------------------------------------------------------------------



        | Make FSWarehouse the only active location



        |--------------------------------------------------------------------------



        */







        $this->makeFsWarehouseOnly(



            $shopifyProduct,



            $fsWarehouseLocationId



        );







        return $shopifyProduct;



    }







    /**



     * Build Shopify product options.



     *



     * Existing Shopify products:



     * - Preserve existing option IDs.



     * - Preserve existing option value IDs.



     * - Add missing values required by incoming variants.



     *



     * New products:



     * - Use supplied product_options when available.



     * - Otherwise derive options from variant option_name/option_value.



     * - Fall back to Title / Default Title for simple products.



     */



    protected function buildProductOptions(



        array $product,



        ?array $existingProduct



    ): array {







        $sourceOptions = [];







        if (



            $existingProduct



            && !empty($existingProduct['options'])



            && is_array($existingProduct['options'])



        ) {



            $sourceOptions = $existingProduct['options'];



        }







        if (empty($sourceOptions)) {



            $sourceOptions =



                $product['product_options']



                ?? [];



        }







        $options = [];







        foreach ($sourceOptions as $index => $option) {







            if (!is_array($option)) {



                continue;



            }







            $name = trim(



                (string) (



                    $option['name']



                    ?? $option['option_name']



                    ?? ''



                )



            );







            if ($name === '') {



                continue;



            }







            $position =



                (int) (



                    $option['position']



                    ?? ($index + 1)



                );







            if ($position < 1) {



                $position = $index + 1;



            }







            $normalizedOption = [



                'name' => $name,



                'position' => $position,



                'values' => [],



            ];







            if (!empty($option['id'])) {



                $normalizedOption['id'] =



                    $option['id'];



            }







            /*



            |--------------------------------------------------------------------------



            | Existing Shopify option values



            |--------------------------------------------------------------------------



            */







            $optionValues =



                $option['optionValues']



                ?? [];







            if (



                !empty($optionValues)



                && is_array($optionValues)



            ) {



                foreach ($optionValues as $optionValue) {







                    if (!is_array($optionValue)) {



                        continue;



                    }







                    $valueName = trim(



                        (string) (



                            $optionValue['name']



                            ?? ''



                        )



                    );







                    if ($valueName === '') {



                        continue;



                    }







                    $value = [



                        'name' => $valueName,



                    ];







                    if (!empty($optionValue['id'])) {



                        $value['id'] =



                            $optionValue['id'];



                    }







                    $this->appendUniqueOptionValue(



                        $normalizedOption['values'],



                        $value



                    );



                }



            }







            /*



            |--------------------------------------------------------------------------



            | Fallback for string values



            |--------------------------------------------------------------------------



            */







            if (



                isset($option['values'])



                && is_array($option['values'])



            ) {



                foreach ($option['values'] as $value) {







                    if (is_array($value)) {







                        $valueName = trim(



                            (string) (



                                $value['name']



                                ?? ''



                            )



                        );







                        if ($valueName === '') {



                            continue;



                        }







                        $normalizedValue = [



                            'name' => $valueName,



                        ];







                        if (!empty($value['id'])) {



                            $normalizedValue['id'] =



                                $value['id'];



                        }







                    } else {







                        $valueName =



                            trim((string) $value);







                        if ($valueName === '') {



                            continue;



                        }







                        $normalizedValue = [



                            'name' => $valueName,



                        ];



                    }







                    $this->appendUniqueOptionValue(



                        $normalizedOption['values'],



                        $normalizedValue



                    );



                }



            }







            $options[] = $normalizedOption;



        }







        $variants =



            $product['variants']



            ?? [];







        /*



        |--------------------------------------------------------------------------



        | New product with no explicit product options:



        | derive them from Fullscript variants.



        |--------------------------------------------------------------------------



        */







        if (



            empty($options)



            && is_array($variants)



        ) {



            foreach ($variants as $variant) {







                if (!is_array($variant)) {



                    continue;



                }







                $optionName = trim(



                    (string) (



                        $variant['option_name']



                        ?? $variant['optionName']



                        ?? ''



                    )



                );







                $optionValue = trim(



                    (string) (



                        $variant['option_value']



                        ?? $variant['optionValue']



                        ?? ''



                    )



                );







                if (



                    $optionName === ''



                    || $optionValue === ''



                ) {



                    continue;



                }







                $optionIndex = null;







                foreach ($options as $index => $option) {







                    if (



                        strcasecmp(



                            $option['name'],



                            $optionName



                        ) === 0



                    ) {



                        $optionIndex = $index;



                        break;



                    }



                }







                if ($optionIndex === null) {







                    $options[] = [



                        'name' => $optionName,



                        'position' => count($options) + 1,



                        'values' => [],



                    ];







                    $optionIndex =



                        count($options) - 1;



                }







                $this->appendUniqueOptionValue(



                    $options[$optionIndex]['values'],



                    [



                        'name' => $optionValue,



                    ]



                );



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Add variant values to matching options.



        |--------------------------------------------------------------------------



        */







        if (



            !empty($options)



            && is_array($variants)



        ) {



            foreach ($variants as $variant) {







                if (!is_array($variant)) {



                    continue;



                }







                $optionName = trim(



                    (string) (



                        $variant['option_name']



                        ?? $variant['optionName']



                        ?? ''



                    )



                );







                $optionValue = trim(



                    (string) (



                        $variant['option_value']



                        ?? $variant['optionValue']



                        ?? ''



                    )



                );







                if ($optionValue === '') {



                    continue;



                }







                $optionIndex = null;







                if ($optionName !== '') {



                    foreach ($options as $index => $option) {







                        if (



                            strcasecmp(



                                $option['name'],



                                $optionName



                            ) === 0



                        ) {



                            $optionIndex = $index;



                            break;



                        }



                    }



                }







                /*



                |--------------------------------------------------------------------------



                | A simple product normally has one option.



                |--------------------------------------------------------------------------



                */







                if (



                    $optionIndex === null



                    && count($options) === 1



                ) {



                    $optionIndex = 0;



                }







                if ($optionIndex === null) {



                    continue;



                }







                $this->appendUniqueOptionValue(



                    $options[$optionIndex]['values'],



                    [



                        'name' => $optionValue,



                    ]



                );



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Safe fallback for simple products.



        |--------------------------------------------------------------------------



        */







        if (empty($options)) {



            $options = [



                [



                    'name' => 'Title',



                    'position' => 1,



                    'values' => [



                        [



                            'name' => 'Default Title',



                        ],



                    ],



                ],



            ];



        }







        /*



        |--------------------------------------------------------------------------



        | Every option needs at least one value.



        |--------------------------------------------------------------------------



        */







        foreach ($options as $index => &$option) {







            $option['position'] =



                $index + 1;







            if (empty($option['values'])) {



                $option['values'] = [



                    [



                        'name' => 'Default Title',



                    ],



                ];



            }



        }







        unset($option);







        /*



        |--------------------------------------------------------------------------



        | Shopify supports a maximum of 3 product options.



        |--------------------------------------------------------------------------



        */







        return array_values(



            array_slice($options, 0, 3)



        );



    }







    /**



     * Build variant option values that match productOptions.



     *



     * Existing Shopify variants are preferred so an existing variant's



     * option configuration is not accidentally changed.



     */



    protected function buildVariantOptionValues(



        array $variant,



        ?array $existingVariant,



        array $productOptions



    ): array {







        /*



        |--------------------------------------------------------------------------



        | Existing Shopify variant



        |--------------------------------------------------------------------------



        */







        if (



            $existingVariant



            && !empty($existingVariant['selectedOptions'])



            && is_array($existingVariant['selectedOptions'])



        ) {







            $selectedOptions = [];







            foreach (



                $existingVariant['selectedOptions']



                as $selectedOption



            ) {







                if (!is_array($selectedOption)) {



                    continue;



                }







                $optionName = trim(



                    (string) (



                        $selectedOption['name']



                        ?? ''



                    )



                );







                $valueName = trim(



                    (string) (



                        $selectedOption['value']



                        ?? (



                            $selectedOption['optionValue']['name']



                            ?? ''



                        )



                    )



                );







                if (



                    $optionName === ''



                    || $valueName === ''



                ) {



                    continue;



                }







                $selectedOptions[] = [



                    'optionName' => $optionName,



                    'name' => $valueName,



                ];



            }







            if (!empty($selectedOptions)) {



                return $selectedOptions;



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Fullscript option_values



        |--------------------------------------------------------------------------



        */







        $candidateOptions = [];







        if (



            !empty($variant['option_values'])



            && is_array($variant['option_values'])



        ) {







            foreach (



                $variant['option_values']



                as $optionValue



            ) {







                if (!is_array($optionValue)) {



                    continue;



                }







                $optionName = trim(



                    (string) (



                        $optionValue['optionName']



                        ?? $optionValue['option_name']



                        ?? ''



                    )



                );







                $valueName = trim(



                    (string) (



                        $optionValue['name']



                        ?? $optionValue['value']



                        ?? $optionValue['option_value']



                        ?? ''



                    )



                );







                if (



                    $optionName === ''



                    || $valueName === ''



                ) {



                    continue;



                }







                $candidateOptions[] = [



                    'optionName' => $optionName,



                    'name' => $valueName,



                ];



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Legacy Fullscript option fields



        |--------------------------------------------------------------------------



        */







        if (empty($candidateOptions)) {







            $optionName = trim(



                (string) (



                    $variant['option_name']



                    ?? $variant['optionName']



                    ?? ''



                )



            );







            $valueName = trim(



                (string) (



                    $variant['option_value']



                    ?? $variant['optionValue']



                    ?? ''



                )



            );







            if (



                $optionName !== ''



                && $valueName !== ''



            ) {



                $candidateOptions[] = [



                    'optionName' => $optionName,



                    'name' => $valueName,



                ];



            }



        }







        /*



        |--------------------------------------------------------------------------



        | Match Fullscript options to Shopify options.



        |--------------------------------------------------------------------------



        */







        $matchedOptions = [];







        foreach ($candidateOptions as $candidate) {







            $candidateName =



                $candidate['optionName'];







            $candidateValue =



                $candidate['name'];







            $matchedOption = null;







            foreach ($productOptions as $productOption) {







                if (



                    strcasecmp(



                        (string) (



                            $productOption['name']



                            ?? ''



                        ),



                        $candidateName



                    ) === 0



                ) {



                    $matchedOption =



                        $productOption;







                    break;



                }



            }







            /*



            |--------------------------------------------------------------------------



            | Simple product fallback.



            |--------------------------------------------------------------------------



            */







            if (



                !$matchedOption



                && count($productOptions) === 1



            ) {



                $matchedOption =



                    $productOptions[0];



            }







            if (!$matchedOption) {



                continue;



            }







            $optionName =



                $matchedOption['name'];







            $allowedValue = null;







            foreach (



                $matchedOption['values']



                ?? []



                as $allowed



            ) {







                $allowedName =



                    is_array($allowed)



                        ? (



                            $allowed['name']



                            ?? ''



                        )



                        : (string) $allowed;







                if (



                    strcasecmp(



                        trim($allowedName),



                        $candidateValue



                    ) === 0



                ) {



                    $allowedValue =



                        $allowedName;







                    break;



                }



            }







            /*



            |--------------------------------------------------------------------------



            | buildProductOptions() normally already added this value.



            |--------------------------------------------------------------------------



            */







            if ($allowedValue === null) {



                $allowedValue =



                    $candidateValue;



            }







            $matchedOptions[] = [



                'optionName' => $optionName,



                'name' => $allowedValue,



            ];



        }







        if (!empty($matchedOptions)) {



            return $matchedOptions;



        }







        /*



        |--------------------------------------------------------------------------



        | Final fallback.



        |--------------------------------------------------------------------------



        */







        $firstOption =



            $productOptions[0]



            ?? [



                'name' => 'Title',



                'values' => [



                    [



                        'name' => 'Default Title',



                    ],



                ],



            ];







        $firstValue =



            $firstOption['values'][0]



            ?? [



                'name' => 'Default Title',



            ];







        $firstValueName =



            is_array($firstValue)



                ? (



                    $firstValue['name']



                    ?? 'Default Title'



                )



                : (string) $firstValue;







        return [



            [



                'optionName' =>



                    $firstOption['name']



                    ?? 'Title',







                'name' =>



                    $firstValueName,



            ],



        ];



    }







    /**



     * Add an option value without creating duplicates.



     */



    protected function appendUniqueOptionValue(



        array &$values,



        array $newValue



    ): void {







        $newName = trim(



            (string) (



                $newValue['name']



                ?? ''



            )



        );







        if ($newName === '') {



            return;



        }







        foreach ($values as $existingValue) {







            $existingName =



                is_array($existingValue)



                    ? (



                        $existingValue['name']



                        ?? ''



                    )



                    : (string) $existingValue;







            if (



                strcasecmp(



                    trim($existingName),



                    $newName



                ) === 0



            ) {



                return;



            }



        }







        $values[] = $newValue;



    }







    /**



     * Get FSWarehouse location ID dynamically.



     */



    protected function getFsWarehouseLocationId(): string



    {



        $shopifyToken =



            ShopifyToken::query()->first();







        if (!$shopifyToken) {







            throw new RuntimeException(



                'Shopify token was not found.'



            );



        }







        $locationId =



            $shopifyToken->fulfillment_location_id;







        if (!$locationId) {







            throw new RuntimeException(



                'Shopify FSWarehouse location ID is not configured.'



            );



        }







        $query = <<<'GRAPHQL'



query GetLocation($id: ID!) {



    location(id: $id) {



        id



        name



        isActive



    }



}



GRAPHQL;







        $data =



            $this->graphql->execute(



                $query,



                [



                    'id' =>



                        $locationId,



                ]



            );







        $location =



            $data['location']



            ?? null;







        if (!$location) {







            throw new RuntimeException(



                "Shopify location {$locationId} was not found."



            );



        }







        if (



            strtolower(



                trim(



                    (string) (



                        $location['name']



                        ?? ''



                    )



                )



            ) !== 'fswarehouse'



        ) {







            throw new RuntimeException(



                "Configured fulfillment location is '{$location['name']}', not FSWarehouse."



            );



        }







        return $location['id'];



    }







    /**



     * Activate inventory at FSWarehouse.



     */



    protected function activateInventoryLocation(



        string $inventoryItemId,



        string $locationId



    ): void {







        $mutation = <<<'GRAPHQL'



mutation ActivateInventoryLocation(



    $inventoryItemId: ID!,



    $inventoryItemUpdates: [InventoryBulkToggleActivationInput!]!



) {



    inventoryBulkToggleActivation(



        inventoryItemId: $inventoryItemId,



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



            $this->graphql->execute(



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







        if (!empty($errors)) {







            throw new RuntimeException(



                'Unable to activate FSWarehouse: '



                .



                json_encode(



                    $errors,



                    JSON_PRETTY_PRINT



                )



            );



        }



    }







    /**



     * Make FSWarehouse the only active inventory location.



     */



    protected function makeFsWarehouseOnly(



        array $shopifyProduct,



        string $fsWarehouseLocationId



    ): void {







        foreach (



            $shopifyProduct[



                'variants'



            ]['nodes'] ?? []



            as $variant



        ) {







            $inventoryItemId =



                $variant[



                    'inventoryItem'



                ]['id']



                ?? null;







            if (!$inventoryItemId) {



                continue;



            }







            $this->deactivateOtherLocations(



                $inventoryItemId,



                $fsWarehouseLocationId



            );



        }



    }







    /**



     * Deactivate all active locations except FSWarehouse.



     */



    protected function deactivateOtherLocations(



        string $inventoryItemId,



        string $fsWarehouseLocationId



    ): void {







        $query = <<<'GRAPHQL'



query GetInventoryLevels($id: ID!) {



    inventoryItem(id: $id) {



        id







        inventoryLevels(



            first: 50



            includeInactive: false



        ) {



            nodes {



                location {



                    id



                    name



                }







                isActive



            }



        }



    }



}



GRAPHQL;







        $data =



            $this->graphql->execute(



                $query,



                [



                    'id' =>



                        $inventoryItemId,



                ]



            );







        $levels =



            $data[



                'inventoryItem'



            ]['inventoryLevels']['nodes']



            ?? [];







        foreach ($levels as $level) {







            $locationId =



                $level[



                    'location'



                ]['id']



                ?? null;







            if (!$locationId) {



                continue;



            }







            if (



                $locationId ===



                $fsWarehouseLocationId



            ) {



                continue;



            }







            if (



                !(



                    $level['isActive']



                    ?? false



                )



            ) {



                continue;



            }







            $this->deactivateInventoryLocation(



                $inventoryItemId,



                $locationId



            );



        }



    }







    /**



     * Deactivate inventory at a location.



     */



    protected function deactivateInventoryLocation(



        string $inventoryItemId,



        string $locationId



    ): void {







        $mutation = <<<'GRAPHQL'



mutation DeactivateInventoryLocation(



    $inventoryItemId: ID!,



    $inventoryItemUpdates: [InventoryBulkToggleActivationInput!]!



) {



    inventoryBulkToggleActivation(



        inventoryItemId: $inventoryItemId,



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



            $this->graphql->execute(



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







        if (!empty($errors)) {







            throw new RuntimeException(



                'Unable to deactivate inventory location : '



                .



                json_encode(



                    $errors,



                    JSON_PRETTY_PRINT



                )



            );



        }



    }



} 