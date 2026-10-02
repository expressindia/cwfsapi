<?php

namespace App\Http\Controllers;

use App\Services\Fullscript\FullscriptProductService;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Throwable;

class ProductsController extends Controller
{
    public function __construct(
        protected FullscriptProductService $fullscript,
        protected ShopifyProductService $shopify,
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

        $brand = trim(
            (string) $request->input(
                'brand',
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
        | Do not load the catalog until a brand is entered
        |--------------------------------------------------------------------------
        |
        | This prevents the page from loading thousands of products just to
        | perform a local brand search.
        |
        */

        if ($brand === '') {
            $paginator = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                1,
                [
                    'path' => route(
                        'products.index'
                    ),
                    'query' => $request->except(
                        'page'
                    ),
                ]
            );

            return view(
                'products.index',
                [
                    'products' => $paginator,
                    'paginator' => $paginator,
                    'totalProducts' => 0,
                    'perPage' => $perPage,
                    'brand' => $brand,
                    'search' => $search,
                    'error' => null,
                    'lastSyncedAt' => session(
                        'products.last_synced_at'
                    ),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Search Fullscript
        |--------------------------------------------------------------------------
        */

        try {
            $response =
                $this->fullscript->searchProducts(
                    $brand,
                    $search !== ''
                        ? $search
                        : null,
                    $page,
                    $perPage
                );

            /*
            |--------------------------------------------------------------------------
            | Extract products
            |--------------------------------------------------------------------------
            */

            $rawProducts =
                $response['products']
                ?? $response['data']
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

            $total =
                (int) (
                    $meta['total']
                    ?? $meta['total_count']
                    ?? $response['total']
                    ?? 0
                );

            $lastPage =
                (int) (
                    $meta['total_pages']
                    ?? $meta['last_page']
                    ?? $response['total_pages']
                    ?? 1
                );

            /*
            |--------------------------------------------------------------------------
            | Normalize products
            |--------------------------------------------------------------------------
            */

            $seen = [];

            foreach ($rawProducts as $rawProduct) {
                if (
                    isset($rawProduct['attributes'])
                    && is_array(
                        $rawProduct['attributes']
                    )
                ) {
                    $product = array_merge(
                        [
                            'id' =>
                                $rawProduct['id']
                                ?? null,
                        ],
                        $rawProduct['attributes']
                    );
                } else {
                    $product = $rawProduct;
                }

                $normalized =
                    $this->normalizeProduct(
                        $product
                    );

                /*
                |--------------------------------------------------------------------------
                | Exact brand verification
                |--------------------------------------------------------------------------
                |
                | We still verify the returned product brand locally.
                | This protects us if Fullscript's search endpoint performs
                | a broader/partial brand search.
                |
                */

                if (
                    $brand !== ''
                    && strcasecmp(
                        trim(
                            $normalized['brand']
                        ),
                        trim($brand)
                    ) !== 0
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Optional product/SKU search verification
                |--------------------------------------------------------------------------
                */

                if (
                    $search !== ''
                    && stripos(
                        $normalized['title'],
                        $search
                    ) === false
                    && stripos(
                        $normalized['sku'],
                        $search
                    ) === false
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Remove duplicates
                |--------------------------------------------------------------------------
                */

                $uniqueKey =
                    $normalized['id']
                    ?: (
                        $normalized['sku']
                        . '|'
                        . $normalized['title']
                    );

                if (
                    $uniqueKey !== ''
                    && isset($seen[$uniqueKey])
                ) {
                    continue;
                }

                $seen[$uniqueKey] = true;

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

                $normalized[
                    'action'
                ] =
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
                $total =
                    (($page - 1) * $perPage)
                    + count($products);

                if (
                    count($products)
                    === $perPage
                ) {
                    $total++;
                }
            }

            if ($lastPage <= 0) {
                $lastPage = max(
                    1,
                    (int) ceil(
                        $total / $perPage
                    )
                );
            }
        } catch (Throwable $e) {
            report($e);

            $error =
                $e->getMessage();

            $products = [];

            $total = 0;

            $lastPage = 1;
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

                'totalProducts' =>
                    $paginator->total(),

                'perPage' =>
                    $perPage,

                'brand' =>
                    $brand,

                'search' =>
                    $search,

                'error' =>
                    $error,

                'lastSyncedAt' =>
                    session(
                        'products.last_synced_at'
                    ),
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
            $fullscriptProduct =
                $this->fullscript->getProduct(
                    $productId
                );

            $product =
                $this->normalizeForShopify(
                    $fullscriptProduct
                );

            $shopifyProductId =
                $request->input(
                    'shopify_product_id'
                );

            if (
                blank($shopifyProductId)
            ) {
                $sku =
                    $this->getPrimarySku(
                        $fullscriptProduct
                    );

                if ($sku !== '') {
                    $variant =
                        $this->shopify
                            ->findProductBySku(
                                $sku
                            );

                    $shopifyProductId =
                        $variant[
                            'product'
                        ]['id']
                        ?? null;
                }
            }

            $result =
                $this->shopify->createOrUpdate(
                    $product,
                    $shopifyProductId
                );

            return response()->json(
                [
                    'success' => true,
                    'message' =>
                        $shopifyProductId
                            ? 'Product updated successfully.'
                            : 'Product created successfully.',
                    'data' => $result,
                ]
            );
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
     * Push multiple Fullscript products.
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
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Invalid product selection.',
                ],
                422
            );
        }

        $productIds =
            collect($productIds)
                ->filter(
                    fn ($id) =>
                        is_string($id)
                        && trim($id) !== ''
                )
                ->unique()
                ->values()
                ->all();

        if (empty($productIds)) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Please select at least one product.',
                ],
                422
            );
        }

        $results = [];

        foreach ($productIds as $productId) {
            try {
                $fullscriptProduct =
                    $this->fullscript->getProduct(
                        $productId
                    );

                $product =
                    $this->normalizeForShopify(
                        $fullscriptProduct
                    );

                $sku =
                    $this->getPrimarySku(
                        $fullscriptProduct
                    );

                $shopifyProductId = null;

                if ($sku !== '') {
                    $variant =
                        $this->shopify
                            ->findProductBySku(
                                $sku
                            );

                    $shopifyProductId =
                        $variant[
                            'product'
                        ]['id']
                        ?? null;
                }

                $shopifyResult =
                    $this->shopify->createOrUpdate(
                        $product,
                        $shopifyProductId
                    );

                $results[] = [
                    'id' =>
                        $productId,

                    'success' =>
                        true,

                    'sku' =>
                        $sku,

                    'action' =>
                        $shopifyProductId
                            ? 'updated'
                            : 'created',

                    'data' =>
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
            count($results)
            - $successful;

        return response()->json(
            [
                'success' =>
                    $failed === 0,

                'message' =>
                    sprintf(
                        '%d product(s) processed. %d succeeded, %d failed.',
                        count($results),
                        $successful,
                        $failed
                    ),

                'results' =>
                    $results,
            ]
        );
    }

    /**
     * Normalize Fullscript product for the Products page.
     */
    protected function normalizeProduct(
        array $product
    ): array {
        $id =
            $product['id']
            ?? $product['product_id']
            ?? null;

        $title =
            $product['title']
            ?? $product['name']
            ?? $product['product_name']
            ?? 'Untitled Product';

        $brandValue =
            $product['brand']
            ?? $product['brand_name']
            ?? $product['vendor']
            ?? '';

        if (is_array($brandValue)) {
            $brand =
                $brandValue['name']
                ?? '';
        } else {
            $brand =
                (string) $brandValue;
        }

        $sku =
            $product['sku']
            ?? $product['SKU']
            ?? '';

        if (
            $sku === ''
            && isset(
                $product['primary_variant']
            )
        ) {
            $sku =
                $product[
                    'primary_variant'
                ]['sku']
                ?? '';
        }

        $availability =
            $product['availability']
            ?? $product['availability_status']
            ?? $product['status']
            ?? 'Unknown';

        $image = null;

        if (
            isset($product['image'])
            && is_string(
                $product['image']
            )
        ) {
            $image =
                $product['image'];
        } elseif (
            isset($product['image_url'])
        ) {
            $image =
                $product['image_url'];
        } elseif (
            isset(
                $product[
                    'primary_variant'
                ]
            )
        ) {
            $variant =
                $product[
                    'primary_variant'
                ];

            $image =
                $variant[
                    'image_url_medium'
                ]
                ?? $variant[
                    'image_url_small'
                ]
                ?? $variant[
                    'image_url_large'
                ]
                ?? null;
        } elseif (
            isset(
                $product['images']
            )
            && is_array(
                $product['images']
            )
        ) {
            $firstImage =
                $product['images'][0]
                ?? null;

            if (is_string($firstImage)) {
                $image =
                    $firstImage;
            } elseif (
                is_array(
                    $firstImage
                )
            ) {
                $image =
                    $firstImage['url']
                    ?? $firstImage['src']
                    ?? null;
            }
        }

        $updatedAt =
            $product['updated_at']
            ?? $product['updatedAt']
            ?? null;

        return [
            'id' =>
                $id,

            'title' =>
                (string) $title,

            'brand' =>
                trim(
                    $brand
                ),

            'sku' =>
                trim(
                    (string) $sku
                ),

            'image' =>
                $image,

            'availability' =>
                $this->formatAvailability(
                    $availability
                ),

            'updated_at' =>
                $this->formatDate(
                    $updatedAt
                ),

            'raw' =>
                $product,

            'shopify_status' =>
                'Not Checked',

            'shopify_status_text' =>
                '',

            'shopify_product_id' =>
                null,

            'action' =>
                'push',
        ];
    }

    /**
     * Normalize Fullscript product for ShopifyProductService.
     */
    protected function normalizeForShopify(
        array $response
    ): array {
        $product =
            $response['product']
            ?? $response['data']
            ?? $response;

        if (
            isset(
                $product['attributes']
            )
            && is_array(
                $product['attributes']
            )
        ) {
            $product =
                array_merge(
                    [
                        'id' =>
                            $product['id']
                            ?? null,
                    ],
                    $product[
                        'attributes'
                    ]
                );
        }

        $brandValue =
            $product['brand']
            ?? $product['brand_name']
            ?? $product['vendor']
            ?? 'Fullscript';

        if (is_array($brandValue)) {
            $brand =
                $brandValue['name']
                ?? 'Fullscript';
        } else {
            $brand =
                (string) $brandValue;
        }

        $title =
            $product['title']
            ?? $product['name']
            ?? $product['product_name']
            ?? 'Untitled Product';

        $description =
            $product['description_html']
            ?? $product['descriptionHtml']
            ?? $product['description']
            ?? '';

        $variants =
            $product['variants']
            ?? [];

        if (
            isset(
                $variants['nodes']
            )
        ) {
            $variants =
                $variants['nodes'];
        }

        if (
            !is_array($variants)
            || empty($variants)
        ) {
            $primary =
                $product[
                    'primary_variant'
                ]
                ?? [];

            if (!empty($primary)) {
                $variants = [
                    $primary,
                ];
            }
        }

        $shopifyVariants = [];

        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                continue;
            }

            $sku =
                trim(
                    (string) (
                        $variant['sku']
                        ?? ''
                    )
                );

            if ($sku === '') {
                continue;
            }

            $optionValues = [];

            if (
                isset(
                    $variant[
                        'option_values'
                    ]
                )
                && is_array(
                    $variant[
                        'option_values'
                    ]
                )
            ) {
                $optionValues =
                    $variant[
                        'option_values'
                    ];
            }

            $shopifyVariants[] = [
                'sku' =>
                    $sku,

                'barcode' =>
                    $variant['upc']
                    ?? $variant['barcode']
                    ?? null,

                'price' =>
                    $variant['msrp']
                    ?? $variant['price']
                    ?? '0.00',

                'compare_at_price' =>
                    $variant[
                        'compare_at_price'
                    ]
                    ?? null,

                'cost' =>
                    $variant['cost']
                    ?? null,

                'option_values' =>
                    $optionValues,

                'inventory_quantity' =>
                    0,
            ];
        }

        if (
            empty($shopifyVariants)
        ) {
            $sku =
                $this->getPrimarySku(
                    $product
                );

            if ($sku !== '') {
                $primary =
                    $product[
                        'primary_variant'
                    ]
                    ?? [];

                $shopifyVariants[] = [
                    'sku' =>
                        $sku,

                    'barcode' =>
                        $primary['upc']
                        ?? null,

                    'price' =>
                        $primary['msrp']
                        ?? '0.00',

                    'compare_at_price' =>
                        null,

                    'cost' =>
                        null,

                    'option_values' =>
                        [],

                    'inventory_quantity' =>
                        0,
                ];
            }
        }

        return [
            'title' =>
                (string) $title,

            'description_html' =>
                (string) $description,

            'vendor' =>
                trim($brand)
                !== ''
                    ? trim($brand)
                    : 'Fullscript',

            'product_type' =>
                $product['product_type']
                ?? 'Vitamins & Supplements',

            'status' =>
                'ACTIVE',

            'gift_card' =>
                false,

            'tags' =>
                $product['tags']
                ?? [],

            'product_options' =>
                $this->buildProductOptions(
                    $product,
                    $shopifyVariants
                ),

            'variants' =>
                $shopifyVariants,

            'images' =>
                $this->buildShopifyImages(
                    $product
                ),

            'seo' =>
                $product['seo']
                ?? [],
        ];
    }

    /**
     * Build Shopify product options.
     */
    protected function buildProductOptions(
        array $product,
        array $variants
    ): array {
        if (
            !empty(
                $product[
                    'product_options'
                ]
            )
            && is_array(
                $product[
                    'product_options'
                ]
            )
        ) {
            return $product[
                'product_options'
            ];
        }

        return [];
    }

    /**
     * Build Shopify images.
     */
    protected function buildShopifyImages(
        array $product
    ): array {
        $images =
            $product['images']
            ?? [];

        if (
            !is_array($images)
        ) {
            $images = [];
        }

        $result = [];

        foreach ($images as $image) {
            if (is_string($image)) {
                $result[] = [
                    'url' =>
                        $image,
                ];

                continue;
            }

            if (!is_array($image)) {
                continue;
            }

            $url =
                $image['url']
                ?? $image['src']
                ?? null;

            if ($url) {
                $result[] = [
                    'url' =>
                        $url,
                ];
            }
        }

        if (
            empty($result)
            && isset(
                $product[
                    'primary_variant'
                ]
            )
        ) {
            $variant =
                $product[
                    'primary_variant'
                ];

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
                $result[] = [
                    'url' =>
                        $url,
                ];
            }
        }

        return $result;
    }

    /**
     * Get primary SKU.
     */
    protected function getPrimarySku(
        array $product
    ): string {
        if (
            isset(
                $product[
                    'primary_variant'
                ]['sku']
            )
        ) {
            return trim(
                (string) $product[
                    'primary_variant'
                ]['sku']
            );
        }

        if (
            isset(
                $product['sku']
            )
        ) {
            return trim(
                (string) $product['sku']
            );
        }

        $variants =
            $product['variants']
            ?? [];

        if (
            isset(
                $variants['nodes']
            )
        ) {
            $variants =
                $variants['nodes'];
        }

        if (is_array($variants)) {
            foreach ($variants as $variant) {
                $sku =
                    trim(
                        (string) (
                            $variant['sku']
                            ?? ''
                        )
                    );

                if ($sku !== '') {
                    return $sku;
                }
            }
        }

        return '';
    }

    /**
     * Convert availability into a display value.
     */
    protected function formatAvailability(
        mixed $availability
    ): string {
        $value =
            strtolower(
                trim(
                    (string) $availability
                )
            );

        return match ($value) {
            'in_stock',
            'in-stock',
            'available',
            'active',
            'in stock' =>
                'In Stock',

            'backordered',
            'backorder' =>
                'Backordered',

            'out_of_stock',
            'out-of-stock',
            'out of stock' =>
                'Out of Stock',

            'discontinued' =>
                'Discontinued',

            default =>
                $availability
                    ? ucwords(
                        str_replace(
                            ['_', '-'],
                            ' ',
                            $value
                        )
                    )
                    : 'Unknown',
        };
    }

    /**
     * Format Fullscript updated date.
     */
    protected function formatDate(
        mixed $date
    ): ?string {
        if (!$date) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse(
                $date
            )->format(
                'M d, Y h:i A'
            );
        } catch (Throwable) {
            return (string) $date;
        }
    }

    /**
     * Check whether SKU exists in Shopify.
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

            $productId =
                $variant[
                    'product'
                ]['id']
                ?? null;

            return [
                'status' =>
                    'Exists',

                'text' =>
                    'Will update',

                'product_id' =>
                    $productId,

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
}