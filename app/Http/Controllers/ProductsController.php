<?php

namespace App\Http\Controllers;

use App\Services\Fullscript\FullscriptProductService;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ProductsController extends Controller
{
    public function __construct(
        protected FullscriptProductService $fullscript,
        protected ShopifyProductService $shopify
    ) {
    }

    /**
     * Display Fullscript products.
     */
    public function index(Request $request)
    {
        $page = max(
            1,
            (int) $request->input('page', 1)
        );

        $perPage = (int) $request->input(
            'per_page',
            25
        );

        if (!in_array(
            $perPage,
            [25, 50, 100],
            true
        )) {
            $perPage = 25;
        }

        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        $brandId = trim(
            (string) $request->input(
                'brand_id',
                ''
            )
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $products = [];

        $total = 0;

        $lastPage = 1;

        $error = null;

        /*
        |--------------------------------------------------------------------------
        | Load brands
        |--------------------------------------------------------------------------
        */

        $brands = [];

        try {
            $brandResponse =
                $this->fullscript->getBrands();

            $brands =
                $this->extractBrands(
                    $brandResponse
                );
        } catch (Throwable $e) {
            report($e);

            $error =
                $e->getMessage();
        }

        /*
        |--------------------------------------------------------------------------
        | Load products
        |--------------------------------------------------------------------------
        */

        try {
            $response =
                $this->fullscript->searchProducts(
                    $brandId !== ''
                        ? $brandId
                        : null,
                    $search !== ''
                        ? $search
                        : null,
                    $page,
                    $perPage
                );

            $rawProducts =
                $response['data']
                ?? $response['products']
                ?? [];

            if (!is_array($rawProducts)) {
                $rawProducts = [];
            }

            /*
            |--------------------------------------------------------------------------
            | Pagination metadata
            |--------------------------------------------------------------------------
            */

            $meta =
                $response['meta']
                ?? [];

            $paginationData =
                $meta['pagination']
                ?? $meta['page']
                ?? $meta;

            $total =
                (int) (
                    $paginationData['total']
                    ?? $meta['total']
                    ?? $response['total']
                    ?? 0
                );

            $lastPage =
                (int) (
                    $paginationData['total_pages']
                    ?? $paginationData['last_page']
                    ?? $meta['total_pages']
                    ?? $response['total_pages']
                    ?? 1
                );

            /*
            |--------------------------------------------------------------------------
            | Normalize products
            |--------------------------------------------------------------------------
            */

            $seen = [];

            foreach ($rawProducts as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $normalized =
                    $this->normalizeProduct(
                        $product
                    );

                /*
                |--------------------------------------------------------------------------
                | Prevent duplicate Fullscript products
                |--------------------------------------------------------------------------
                */

                $uniqueKey =
                    ($normalized['id'] ?? '')
                    . '|'
                    . ($normalized['sku'] ?? '');

                if (
                    $uniqueKey !== '|'
                    && isset($seen[$uniqueKey])
                ) {
                    continue;
                }

                if ($uniqueKey !== '|') {
                    $seen[$uniqueKey] = true;
                }

                /*
                |--------------------------------------------------------------------------
                | Shopify status
                |--------------------------------------------------------------------------
                */

                $shopifyInfo =
                    $this->getShopifyStatus(
                        $normalized['sku']
                    );

                $normalized[
                    'shopify_status'
                ] =
                    $shopifyInfo['status'];

                $normalized[
                    'shopify_status_text'
                ] =
                    $shopifyInfo['text'];

                $normalized[
                    'shopify_product_id'
                ] =
                    $shopifyInfo['product_id'];

                $normalized['action'] =
                    $shopifyInfo['action'];

                $products[] =
                    $normalized;
            }

            /*
            |--------------------------------------------------------------------------
            | Fallback pagination
            |--------------------------------------------------------------------------
            */

            if ($total <= 0) {
                if (
                    count($rawProducts)
                    >= $perPage
                ) {
                    $total =
                        (($page - 1) * $perPage)
                        + count($rawProducts)
                        + 1;
                } else {
                    $total =
                        (($page - 1) * $perPage)
                        + count($rawProducts);
                }
            }

            if ($lastPage <= 0) {
                $lastPage =
                    max(
                        1,
                        (int) ceil(
                            $total / $perPage
                        )
                    );
            }
        } catch (Throwable $e) {
            report($e);

            if (empty($error)) {
                $error =
                    $e->getMessage();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Laravel paginator
        |--------------------------------------------------------------------------
        */

        $paginator =
            new LengthAwarePaginator(
                $products,
                $total,
                $perPage,
                $page,
                [
                    'path' =>
                        route(
                            'products.index'
                        ),

                    'query' =>
                        $request->except(
                            'page'
                        ),
                ]
            );

        return view(
            'products.index',
            [
                'products' =>
                    $paginator,

                'paginator' =>
                    $paginator,

                'brands' =>
                    $brands,

                'brandId' =>
                    $brandId,

                'search' =>
                    $search,

                'perPage' =>
                    $perPage,

                'totalProducts' =>
                    $total,

                'error' =>
                    $error,

                'lastSyncedAt' =>
                    null,
            ]
        );
    }

    /**
     * Push one Fullscript product to Shopify.
     */
    public function push(
        Request $request,
        string $productId
    ): JsonResponse {
        try {
            /*
            |--------------------------------------------------------------------------
            | Get full product details from Fullscript
            |--------------------------------------------------------------------------
            */

            $response =
                $this->fullscript->getProduct(
                    $productId
                );

            $rawProduct =
                $response['data']
                ?? $response['product']
                ?? $response;

            if (
                !is_array($rawProduct)
                || empty($rawProduct)
            ) {
                throw new \RuntimeException(
                    'Fullscript product was not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Determine existing Shopify product
            |--------------------------------------------------------------------------
            */

            $normalized =
                $this->normalizeProduct(
                    $rawProduct
                );

            $shopifyProductId = null;

            if (!empty($normalized['sku'])) {
                $shopifyInfo =
                    $this->getShopifyStatus(
                        $normalized['sku']
                    );

                $shopifyProductId =
                    $shopifyInfo[
                        'product_id'
                    ] ?? null;
            }

            /*
            |--------------------------------------------------------------------------
            | Convert Fullscript data
            |--------------------------------------------------------------------------
            */

            $shopifyProduct =
                $this->normalizeForShopify(
                    $rawProduct
                );

            /*
            |--------------------------------------------------------------------------
            | Create / Update
            |--------------------------------------------------------------------------
            */

            $result =
                $this->shopify->createOrUpdate(
                    $shopifyProduct,
                    $shopifyProductId
                );

            return response()->json([
                'success' => true,

                'message' =>
                    $shopifyProductId
                        ? 'Product updated successfully.'
                        : 'Product created successfully.',

                'product' =>
                    $result,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        $e->getMessage(),
                ],
                422
            );
        }
    }

    /**
     * Push selected products to Shopify.
     */
    public function pushSelected(
        Request $request
    ): JsonResponse {
        $productIds =
            $request->input(
                'product_ids',
                []
            );

        if (!is_array($productIds)) {
            $productIds = [];
        }

        $productIds =
            array_values(
                array_filter(
                    $productIds
                )
            );

        if (empty($productIds)) {
            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        'No products were selected.',
                ],
                422
            );
        }

        $results = [];

        foreach ($productIds as $productId) {
            try {
                $response =
                    $this->fullscript->getProduct(
                        (string) $productId
                    );

                $rawProduct =
                    $response['data']
                    ?? $response['product']
                    ?? $response;

                if (
                    !is_array($rawProduct)
                    || empty($rawProduct)
                ) {
                    throw new \RuntimeException(
                        'Fullscript product was not found.'
                    );
                }

                $normalized =
                    $this->normalizeProduct(
                        $rawProduct
                    );

                $shopifyProductId = null;

                if (!empty($normalized['sku'])) {
                    $shopifyInfo =
                        $this->getShopifyStatus(
                            $normalized['sku']
                        );

                    $shopifyProductId =
                        $shopifyInfo[
                            'product_id'
                        ] ?? null;
                }

                $shopifyProduct =
                    $this->normalizeForShopify(
                        $rawProduct
                    );

                $shopifyResult =
                    $this->shopify->createOrUpdate(
                        $shopifyProduct,
                        $shopifyProductId
                    );

                $results[] = [
                    'id' =>
                        $productId,

                    'sku' =>
                        $normalized['sku'],

                    'success' =>
                        true,

                    'message' =>
                        $shopifyProductId
                            ? 'Updated'
                            : 'Created',

                    'result' =>
                        $shopifyResult,
                ];
            } catch (Throwable $e) {
                report($e);

                $results[] = [
                    'id' =>
                        $productId,

                    'success' =>
                        false,

                    'message' =>
                        $e->getMessage(),
                ];
            }
        }

        $successful =
            collect($results)
                ->where(
                    'success',
                    true
                )
                ->count();

        $failed =
            collect($results)
                ->where(
                    'success',
                    false
                )
                ->count();

        return response()->json([
            'success' =>
                $failed === 0,

            'message' =>
                "{$successful} product(s) processed, "
                . "{$failed} failed.",

            'results' =>
                $results,
        ]);
    }

    /**
     * Normalize a Fullscript product for the table.
     */
    protected function normalizeProduct(
        array $product
    ): array {
        $brand =
            $product['brand']
            ?? [];

        $primaryVariant =
            $product['primary_variant']
            ?? $product['primaryVariant']
            ?? [];

        if (!is_array($brand)) {
            $brand = [];
        }

        if (!is_array($primaryVariant)) {
            $primaryVariant = [];
        }

        $image =
            $primaryVariant[
                'image_url_small'
            ]
            ?? $primaryVariant[
                'image_url_medium'
            ]
            ?? $product['image_url']
            ?? $product['image']
            ?? null;

        return [
            'id' =>
                $product['id']
                ?? '',

            'title' =>
                $product['name']
                ?? $product['title']
                ?? 'Untitled Product',

            'brand' =>
                $brand['name']
                ?? $product['brand_name']
                ?? '',

            'brand_id' =>
                $brand['id']
                ?? '',

            'sku' =>
                $primaryVariant['sku']
                ?? $product['sku']
                ?? '',

            'availability' =>
                $primaryVariant[
                    'availability'
                ]
                ?? $product['availability']
                ?? 'Unknown',

            'status' =>
                $primaryVariant['status']
                ?? $product['status']
                ?? null,

            'image' =>
                $image,

            'updated_at' =>
                $product['updated_at']
                ?? $product['updatedAt']
                ?? null,

            'variant_count' =>
                $product['variant_count']
                ?? $product['variantCount']
                ?? 1,

            'raw' =>
                $product,

            'shopify_status' =>
                null,

            'shopify_status_text' =>
                null,

            'shopify_product_id' =>
                null,

            'action' =>
                null,
        ];
    }

    /**
     * Extract brands from Fullscript response.
     */
    protected function extractBrands(
        array $response
    ): array {
        $brands =
            $response['data']
            ?? $response['brands']
            ?? [];

        if (!is_array($brands)) {
            return [];
        }

        $result = [];

        foreach ($brands as $brand) {
            if (!is_array($brand)) {
                continue;
            }

            $id =
                $brand['id']
                ?? $brand['brand_id']
                ?? null;

            $name =
                $brand['name']
                ?? $brand['brand_name']
                ?? null;

            if (
                empty($id)
                || empty($name)
            ) {
                continue;
            }

            $result[] = [
                'id' =>
                    (string) $id,

                'name' =>
                    (string) $name,
            ];
        }

        usort(
            $result,
            fn ($a, $b) =>
                strcasecmp(
                    $a['name'],
                    $b['name']
                )
        );

        return $result;
    }

    /**
     * Check Shopify by SKU.
     */
    protected function getShopifyStatus(
        string $sku
    ): array {
        if ($sku === '') {
            return [
                'status' =>
                    'Not Found',

                'text' =>
                    'No SKU',

                'product_id' =>
                    null,

                'action' =>
                    'push',
            ];
        }

        try {
            $variant =
                $this->shopify
                    ->findProductBySku(
                        $sku
                    );

            if (!$variant) {
                return [
                    'status' =>
                        'Not Found',

                    'text' =>
                        'Will create',

                    'product_id' =>
                        null,

                    'action' =>
                        'push',
                ];
            }

            return [
                'status' =>
                    'Exists',

                'text' =>
                    'Will update',

                'product_id' =>
                    $variant[
                        'product'
                    ]['id'] ?? null,

                'action' =>
                    'update',
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'status' =>
                    'Error',

                'text' =>
                    'Unable to check',

                'product_id' =>
                    null,

                'action' =>
                    'retry',
            ];
        }
    }

    /**
     * Convert Fullscript product into the structure expected
     * by ShopifyProductService::createOrUpdate().
     */
    protected function normalizeForShopify(
        array $product
    ): array {
        $brand =
            $product['brand']
            ?? [];

        $primaryVariant =
            $product['primary_variant']
            ?? $product['primaryVariant']
            ?? [];

        if (!is_array($brand)) {
            $brand = [];
        }

        if (!is_array($primaryVariant)) {
            $primaryVariant = [];
        }

        $title =
            $product['name']
            ?? $product['title']
            ?? 'Untitled Product';

        $sku =
            $primaryVariant['sku']
            ?? $product['sku']
            ?? '';

        $vendor =
            $brand['name']
            ?? $product['brand_name']
            ?? 'Fullscript';

        $description =
            $product['description_html']
            ?? $product['descriptionHtml']
            ?? $product['description']
            ?? '';

        $handle =
            $product['handle']
            ?? $this->makeHandle(
                $title,
                $sku
            );

        /*
        |--------------------------------------------------------------------------
        | Variants
        |--------------------------------------------------------------------------
        */

        $variants =
            $this->buildShopifyVariants(
                $product,
                $primaryVariant
            );

        /*
        |--------------------------------------------------------------------------
        | Product options
        |--------------------------------------------------------------------------
        */

        $productOptions =
            $this->buildProductOptions(
                $variants
            );

        /*
        |--------------------------------------------------------------------------
        | Images
        |--------------------------------------------------------------------------
        */

        $images =
            $this->buildShopifyImages(
                $product,
                $title
            );

        /*
        |--------------------------------------------------------------------------
        | Tags
        |--------------------------------------------------------------------------
        */

        $tags = [];

        if (!empty($vendor)) {
            $tags[] = $vendor;
        }

        $tags[] = 'Fullscript';

        if (!empty($product['tags'])) {
            $existingTags =
                is_array(
                    $product['tags']
                )
                    ? $product['tags']
                    : [$product['tags']];

            $tags =
                array_merge(
                    $tags,
                    $existingTags
                );
        }

        $tags =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'strval',
                            $tags
                        )
                    )
                )
            );

        return [
            'title' =>
                $title,

            'description_html' =>
                $description,

            'vendor' =>
                $vendor,

            'product_type' =>
                $product['product_type']
                ?? $product['productType']
                ?? 'Vitamins & Supplements',

            'status' =>
                'ACTIVE',

            'gift_card' =>
                (bool) (
                    $product['gift_card']
                    ?? $product['isGiftCard']
                    ?? false
                ),

            'handle' =>
                $handle,

            'tags' =>
                $tags,

            'product_options' =>
                $productOptions,

            'variants' =>
                $variants,

            'images' =>
                $images,

            'seo' =>
                $product['seo']
                ?? [],
        ];
    }

    /**
     * Build Shopify variants.
     */
    protected function buildShopifyVariants(
        array $product,
        array $primaryVariant
    ): array {
        $sourceVariants =
            $product['variants']
            ?? $product['variant']
            ?? [];

        /*
        |--------------------------------------------------------------------------
        | Normalize variants container
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $sourceVariants['nodes']
            )
            && is_array(
                $sourceVariants['nodes']
            )
        ) {
            $sourceVariants =
                $sourceVariants['nodes'];
        }

        if (!is_array($sourceVariants)) {
            $sourceVariants = [];
        }

        /*
        |--------------------------------------------------------------------------
        | If detailed API doesn't expose variants,
        | use primary variant.
        |--------------------------------------------------------------------------
        */

        if (empty($sourceVariants)) {
            $sourceVariants = [
                $primaryVariant,
            ];
        }

        $variants = [];

        foreach (
            $sourceVariants
            as $index => $variant
        ) {
            if (!is_array($variant)) {
                continue;
            }

            $sku =
                $variant['sku']
                ?? $variant['SKU']
                ?? '';

            if ($sku === '') {
                continue;
            }

            $variantTitle =
                $variant['name']
                ?? $variant['title']
                ?? $variant['display_name']
                ?? '';

            $units =
                $variant['units']
                ?? null;

            $unitOfMeasure =
                $variant['unit_of_measure']
                ?? $variant['unitOfMeasure']
                ?? '';

            $price =
                $variant['price']
                ?? $variant['msrp']
                ?? null;

            $compareAtPrice =
                $variant['compare_at_price']
                ?? $variant['compareAtPrice']
                ?? null;

            $barcode =
                $variant['upc']
                ?? $variant['barcode']
                ?? null;

            $optionValues = [];

            if ($variantTitle !== '') {
                $optionValues[] = [
                    'optionName' =>
                        'Title',

                    'name' =>
                        $variantTitle,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Shopify variant input
            |--------------------------------------------------------------------------
            */

            $shopifyVariant = [
                'sku' =>
                    (string) $sku,

                'price' =>
                    $price !== null
                        ? (string) $price
                        : '0.00',

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
            ];

            if (!empty($barcode)) {
                $shopifyVariant['barcode'] =
                    (string) $barcode;
            }

            if (
                $compareAtPrice !== null
                && $compareAtPrice !== ''
            ) {
                $shopifyVariant[
                    'compareAtPrice'
                ] =
                    (string) $compareAtPrice;
            }

            if (!empty($optionValues)) {
                $shopifyVariant[
                    'optionValues'
                ] =
                    $optionValues;
            }

            $variants[] =
                $shopifyVariant;
        }

        /*
        |--------------------------------------------------------------------------
        | Safety fallback
        |--------------------------------------------------------------------------
        */

        if (empty($variants)) {
            $variants[] = [
                'sku' =>
                    (string) (
                        $primaryVariant['sku']
                        ?? ''
                    ),

                'price' =>
                    (string) (
                        $primaryVariant['msrp']
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
            ];
        }

        return $variants;
    }

    /**
     * Build Shopify product options.
     */
    protected function buildProductOptions(
        array $variants
    ): array {
        $hasDifferentVariants =
            count($variants) > 1;

        if (!$hasDifferentVariants) {
            return [
                [
                    'name' =>
                        'Title',

                    'values' => [
                        [
                            'name' =>
                                'Default Title',
                        ],
                    ],
                ],
            ];
        }

        $values = [];

        foreach ($variants as $variant) {
            foreach (
                $variant['optionValues']
                ?? []
                as $option
            ) {
                if (
                    ($option['optionName'] ?? '')
                    !== 'Title'
                ) {
                    continue;
                }

                $name =
                    $option['name']
                    ?? '';

                if (
                    $name !== ''
                    && !in_array(
                        $name,
                        $values,
                        true
                    )
                ) {
                    $values[] =
                        $name;
                }
            }
        }

        if (empty($values)) {
            $values = [
                'Default Title',
            ];
        }

        return [
            [
                'name' =>
                    'Title',

                'values' =>
                    array_map(
                        fn ($value) => [
                            'name' => $value,
                        ],
                        $values
                    ),
            ],
        ];
    }

    /**
     * Build Shopify images.
     */
    protected function buildShopifyImages(
        array $product,
        string $title
    ): array {
        $images = [];

        $sourceImages =
            $product['images']
            ?? [];

        if (
            isset(
                $sourceImages['nodes']
            )
            && is_array(
                $sourceImages['nodes']
            )
        ) {
            $sourceImages =
                $sourceImages['nodes'];
        }

        if (!is_array($sourceImages)) {
            $sourceImages = [];
        }

        foreach (
            $sourceImages
            as $image
        ) {
            if (!is_array($image)) {
                continue;
            }

            $url =
                $image['url']
                ?? $image['image_url']
                ?? $image['src']
                ?? null;

            if (!$url) {
                continue;
            }

            $images[] = [
                'url' =>
                    $url,

                'alt' =>
                    $image['alt']
                    ?? $title,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Primary variant image fallback
        |--------------------------------------------------------------------------
        */

        if (empty($images)) {
            $variant =
                $product['primary_variant']
                ?? [];

            if (is_array($variant)) {
                $url =
                    $variant[
                        'image_url_large'
                    ]
                    ?? $variant[
                        'image_url_medium'
                    ]
                    ?? $variant[
                        'image_url_small'
                    ]
                    ?? null;

                if ($url) {
                    $images[] = [
                        'url' =>
                            $url,

                        'alt' =>
                            $title,
                    ];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Product image fallback
        |--------------------------------------------------------------------------
        */

        if (empty($images)) {
            $url =
                $product['image_url']
                ?? $product['image']
                ?? null;

            if ($url) {
                $images[] = [
                    'url' =>
                        $url,

                    'alt' =>
                        $title,
                ];
            }
        }

        return $images;
    }

    /**
     * Generate a Shopify handle.
     */
    protected function makeHandle(
        string $title,
        string $sku = ''
    ): string {
        $value =
            $title;

        if ($sku !== '') {
            $value .= '-' . $sku;
        }

        $handle =
            str($value)
                ->slug()
                ->toString();

        return $handle !== ''
            ? $handle
            : 'fullscript-product';
    }
}