<?php

namespace App\Http\Controllers;

use App\Services\Fullscript\FullscriptProductService;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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
        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $page = max(
            1,
            (int) $request->input('page', 1)
        );

        $perPage = (int) $request->input(
            'per_page',
            25
        );

        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        $brand = trim(
            (string) $request->input('brand', '')
        );

        $search = trim(
            (string) $request->input('search', '')
        );

        /*
        |--------------------------------------------------------------------------
        | Default values
        |--------------------------------------------------------------------------
        */

        $products = [];

        $total = 0;

        $lastPage = 1;

        $error = null;

        /*
        |--------------------------------------------------------------------------
        | Load products from Fullscript
        |--------------------------------------------------------------------------
        */

        try {
            $response = $this->fullscript->getProducts(
                $page,
                $perPage
            );

            /*
            |--------------------------------------------------------------------------
            | Extract products
            |--------------------------------------------------------------------------
            |
            | Your Fullscript response uses:
            |
            | {
            |     "products": [],
            |     "meta": {}
            | }
            |
            */

            $rawProducts = $response['products']
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

            $meta = $response['meta']
                ?? [];

            $total = (int) (
                $meta['total_count']
                ?? $meta['total']
                ?? 0
            );

            $lastPage = (int) (
                $meta['total_pages']
                ?? $meta['last_page']
                ?? 1
            );

            /*
            |--------------------------------------------------------------------------
            | Normalize products
            |--------------------------------------------------------------------------
            */

            foreach ($rawProducts as $rawProduct) {
                if (!is_array($rawProduct)) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | JSON:API compatibility
                |--------------------------------------------------------------------------
                */

                if (
                    isset($rawProduct['attributes']) &&
                    is_array($rawProduct['attributes'])
                ) {
                    $product = array_merge(
                        [
                            'id' => $rawProduct['id'] ?? null,
                        ],
                        $rawProduct['attributes']
                    );
                } else {
                    $product = $rawProduct;
                }

                $normalized = $this->normalizeProduct(
                    $product
                );

                /*
                |--------------------------------------------------------------------------
                | Brand filter
                |--------------------------------------------------------------------------
                */

                if (
                    $brand !== '' &&
                    stripos(
                        $normalized['brand'],
                        $brand
                    ) === false
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Product / SKU search
                |--------------------------------------------------------------------------
                */

                if (
                    $search !== '' &&
                    stripos(
                        $normalized['title'],
                        $search
                    ) === false &&
                    stripos(
                        $normalized['sku'],
                        $search
                    ) === false
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Check Shopify
                |--------------------------------------------------------------------------
                */

                $shopifyInfo = $this->getShopifyStatus(
                    $normalized['sku']
                );

                $normalized['shopify_status'] =
                    $shopifyInfo['status'];

                $normalized['shopify_status_text'] =
                    $shopifyInfo['text'];

                $normalized['shopify_product_id'] =
                    $shopifyInfo['product_id'];

                $normalized['action'] =
                    $shopifyInfo['action'];

                $products[] = $normalized;
            }

            /*
            |--------------------------------------------------------------------------
            | Pagination fallback
            |--------------------------------------------------------------------------
            */

            if ($total <= 0) {
                $total =
                    (($page - 1) * $perPage)
                    + count($rawProducts);

                if (count($rawProducts) === $perPage) {
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

            $error = $e->getMessage();
        }

        /*
        |--------------------------------------------------------------------------
        | Laravel paginator
        |--------------------------------------------------------------------------
        */

        $paginator = new LengthAwarePaginator(
            $products,
            $total,
            $perPage,
            $page,
            [
                'path' => route('products.index'),

                'query' => $request->except('page'),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Brand quick filters
        |--------------------------------------------------------------------------
        */

        $brands = [
            'All Brands',
            'Designs for Health',
            'Allergy Research Group',
            'A.C. Grace',
            'Nordic Naturals',
            'Thorne',
            'Metagenics',
        ];

        /*
        |--------------------------------------------------------------------------
        | Return view
        |--------------------------------------------------------------------------
        */

        return view(
            'products.index',
            [
                'products' => $paginator,

                'paginator' => $paginator,

                'brands' => $brands,

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
     *
     * If the product already exists in Shopify,
     * it will be updated.
     */
    public function push(
        Request $request,
        string $productId
    ): JsonResponse {
        try {
            /*
            |--------------------------------------------------------------------------
            | Get full product from Fullscript
            |--------------------------------------------------------------------------
            |
            | The catalog list only contains the primary variant.
            | Therefore we fetch the detailed product before pushing.
            |
            */

            $fullscriptResponse =
                $this->fullscript->getProduct(
                    $productId
                );

            /*
            |--------------------------------------------------------------------------
            | Extract actual product
            |--------------------------------------------------------------------------
            */

            $product = $this->extractDetailedProduct(
                $fullscriptResponse
            );

            if (!$product) {
                throw new \RuntimeException(
                    'Fullscript product was not returned.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Normalize for Shopify
            |--------------------------------------------------------------------------
            */

            $shopifyProduct =
                $this->normalizeForShopify(
                    $product
                );

            /*
            |--------------------------------------------------------------------------
            | SKU
            |--------------------------------------------------------------------------
            */

            $sku = $this->getPrimarySku(
                $product
            );

            if ($sku === '') {
                throw new \RuntimeException(
                    'The Fullscript product does not have a SKU.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Find existing Shopify product
            |--------------------------------------------------------------------------
            */

            $existing =
                $this->shopify->findProductBySku(
                    $sku
                );

            $shopifyProductId = null;

            if ($existing) {
                $shopifyProductId =
                    $existing['product']['id']
                    ?? null;
            }

            /*
            |--------------------------------------------------------------------------
            | Create / Update Shopify product
            |--------------------------------------------------------------------------
            */

            $result =
                $this->shopify->createOrUpdate(
                    $shopifyProduct,
                    $shopifyProductId
                );

            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            return response()->json(
                [
                    'success' => true,

                    'message' =>
                        $shopifyProductId
                            ? 'Product updated successfully in Shopify.'
                            : 'Product created successfully in Shopify.',

                    'action' =>
                        $shopifyProductId
                            ? 'updated'
                            : 'created',

                    'product' =>
                        $result,
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
     * Push multiple Fullscript products to Shopify.
     */
    public function pushSelected(
        Request $request
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate(
            [
                'product_ids' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'product_ids.*' => [
                    'required',
                    'string',
                ],
            ]
        );

        $productIds =
            array_values(
                array_unique(
                    $validated['product_ids']
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Results
        |--------------------------------------------------------------------------
        */

        $results = [];

        $created = 0;

        $updated = 0;

        $failed = 0;

        /*
        |--------------------------------------------------------------------------
        | Process each product
        |--------------------------------------------------------------------------
        */

        foreach ($productIds as $productId) {
            try {
                /*
                |--------------------------------------------------------------------------
                | Get detailed Fullscript product
                |--------------------------------------------------------------------------
                */

                $fullscriptResponse =
                    $this->fullscript->getProduct(
                        $productId
                    );

                $product =
                    $this->extractDetailedProduct(
                        $fullscriptResponse
                    );

                if (!$product) {
                    throw new \RuntimeException(
                        'Fullscript product was not returned.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Normalize
                |--------------------------------------------------------------------------
                */

                $shopifyProduct =
                    $this->normalizeForShopify(
                        $product
                    );

                /*
                |--------------------------------------------------------------------------
                | SKU
                |--------------------------------------------------------------------------
                */

                $sku =
                    $this->getPrimarySku(
                        $product
                    );

                if ($sku === '') {
                    throw new \RuntimeException(
                        'Product does not have a SKU.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Check Shopify
                |--------------------------------------------------------------------------
                */

                $existing =
                    $this->shopify->findProductBySku(
                        $sku
                    );

                $shopifyProductId = null;

                if ($existing) {
                    $shopifyProductId =
                        $existing['product']['id']
                        ?? null;
                }

                /*
                |--------------------------------------------------------------------------
                | Create / update
                |--------------------------------------------------------------------------
                */

                $shopifyResult =
                    $this->shopify->createOrUpdate(
                        $shopifyProduct,
                        $shopifyProductId
                    );

                if ($shopifyProductId) {
                    $updated++;
                } else {
                    $created++;
                }

                $results[] = [
                    'product_id' =>
                        $productId,

                    'sku' =>
                        $sku,

                    'success' =>
                        true,

                    'action' =>
                        $shopifyProductId
                            ? 'updated'
                            : 'created',

                    'shopify_product_id' =>
                        $shopifyResult['id']
                        ?? $shopifyProductId,
                ];
            } catch (Throwable $e) {
                report($e);

                $failed++;

                $results[] = [
                    'product_id' =>
                        $productId,

                    'success' =>
                        false,

                    'action' =>
                        'failed',

                    'message' =>
                        $e->getMessage(),
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json(
            [
                'success' =>
                    $failed === 0,

                'message' =>
                    'Product sync completed.',

                'summary' => [
                    'total' =>
                        count($productIds),

                    'created' =>
                        $created,

                    'updated' =>
                        $updated,

                    'failed' =>
                        $failed,
                ],

                'results' =>
                    $results,
            ],
            $failed > 0 ? 207 : 200
        );
    }

    /**
     * Normalize a Fullscript catalog product for display.
     */
    protected function normalizeProduct(
        array $product
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Product ID
        |--------------------------------------------------------------------------
        */

        $id =
            $product['id']
            ?? $product['product_id']
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | Title
        |--------------------------------------------------------------------------
        */

        $title =
            $product['title']
            ?? $product['name']
            ?? $product['product_name']
            ?? 'Untitled Product';

        /*
        |--------------------------------------------------------------------------
        | Brand
        |--------------------------------------------------------------------------
        */

        $brand = '';

        if (
            isset($product['brand']) &&
            is_array($product['brand'])
        ) {
            $brand =
                $product['brand']['name']
                ?? '';
        } else {
            $brand =
                $product['brand']
                ?? $product['brand_name']
                ?? $product['vendor']
                ?? '';
        }

        /*
        |--------------------------------------------------------------------------
        | Primary variant
        |--------------------------------------------------------------------------
        */

        $primaryVariant =
            isset($product['primary_variant'])
            && is_array($product['primary_variant'])
                ? $product['primary_variant']
                : [];

        /*
        |--------------------------------------------------------------------------
        | SKU
        |--------------------------------------------------------------------------
        */

        $sku =
            $primaryVariant['sku']
            ?? $product['sku']
            ?? $product['SKU']
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | Availability
        |--------------------------------------------------------------------------
        */

        $availability =
            $primaryVariant['availability']
            ?? $primaryVariant['status']
            ?? $product['availability']
            ?? $product['availability_status']
            ?? $product['status']
            ?? 'Unknown';

        /*
        |--------------------------------------------------------------------------
        | Image
        |--------------------------------------------------------------------------
        */

        $image =
            $primaryVariant['image_url_medium']
            ?? $primaryVariant['image_url_small']
            ?? $primaryVariant['image_url_large']
            ?? null;

        if (!$image) {
            $image =
                $product['image_url']
                ?? $product['image']
                ?? null;
        }

        /*
        |--------------------------------------------------------------------------
        | Updated date
        |--------------------------------------------------------------------------
        */

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
                (string) $brand,

            'sku' =>
                trim((string) $sku),

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
     * Extract the actual product from a detailed Fullscript response.
     */
    protected function extractDetailedProduct(
        array $response
    ): ?array {
        /*
        |--------------------------------------------------------------------------
        | Common response formats
        |--------------------------------------------------------------------------
        */

        if (
            isset($response['product']) &&
            is_array($response['product'])
        ) {
            return $response['product'];
        }

        if (
            isset($response['data']) &&
            is_array($response['data'])
        ) {
            /*
            | Some APIs return:
            |
            | {
            |     "data": {
            |         ...
            |     }
            | }
            */

            if (
                isset($response['data']['product']) &&
                is_array($response['data']['product'])
            ) {
                return $response['data']['product'];
            }

            return $response['data'];
        }

        /*
        |--------------------------------------------------------------------------
        | Direct product response
        |--------------------------------------------------------------------------
        */

        if (
            isset($response['id']) ||
            isset($response['product_id'])
        ) {
            return $response;
        }

        return null;
    }

    /**
     * Convert detailed Fullscript product into the structure
     * expected by ShopifyProductService::createOrUpdate().
     */
    protected function normalizeForShopify(
        array $product
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Brand
        |--------------------------------------------------------------------------
        */

        $brand = '';

        if (
            isset($product['brand']) &&
            is_array($product['brand'])
        ) {
            $brand =
                $product['brand']['name']
                ?? '';
        } else {
            $brand =
                $product['brand']
                ?? $product['brand_name']
                ?? $product['vendor']
                ?? 'Fullscript';
        }

        /*
        |--------------------------------------------------------------------------
        | Title
        |--------------------------------------------------------------------------
        */

        $title =
            $product['title']
            ?? $product['name']
            ?? $product['product_name']
            ?? 'Untitled Product';

        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        $description =
            $product['description_html']
            ?? $product['descriptionHtml']
            ?? $product['description']
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | Product type
        |--------------------------------------------------------------------------
        */

        $productType =
            $product['product_type']
            ?? $product['productType']
            ?? 'Vitamins & Supplements';

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        $status =
            strtoupper(
                (string) (
                    $product['shopify_status']
                    ?? 'ACTIVE'
                )
            );

        if (!in_array(
            $status,
            ['ACTIVE', 'DRAFT', 'ARCHIVED'],
            true
        )) {
            $status = 'ACTIVE';
        }

        /*
        |--------------------------------------------------------------------------
        | Handle
        |--------------------------------------------------------------------------
        */

        $handle =
            $product['handle']
            ?? Str::slug($title);

        /*
        |--------------------------------------------------------------------------
        | Tags
        |--------------------------------------------------------------------------
        */

        $tags = [];

        if (
            isset($product['tags']) &&
            is_array($product['tags'])
        ) {
            $tags = $product['tags'];
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure brand is available as a tag
        |--------------------------------------------------------------------------
        */

        if ($brand !== '') {
            $tags[] = $brand;
        }

        $tags = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn ($tag) =>
                            trim((string) $tag),
                        $tags
                    )
                )
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Variants
        |--------------------------------------------------------------------------
        */

        $variants =
            $this->buildShopifyVariants(
                $product
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
        | Product options
        |--------------------------------------------------------------------------
        */

        $productOptions =
            $this->buildProductOptions(
                $variants
            );

        /*
        |--------------------------------------------------------------------------
        | SEO
        |--------------------------------------------------------------------------
        */

        $seo = [];

        if (
            isset($product['seo']) &&
            is_array($product['seo'])
        ) {
            $seo = [
                'title' =>
                    $product['seo']['title']
                    ?? $title,

                'description' =>
                    $product['seo']['description']
                    ?? null,
            ];
        }

        return [
            'title' =>
                (string) $title,

            'description_html' =>
                (string) $description,

            'vendor' =>
                (string) $brand,

            'product_type' =>
                (string) $productType,

            'status' =>
                $status,

            'gift_card' =>
                false,

            'tags' =>
                $tags,

            'handle' =>
                $handle,

            'product_options' =>
                $productOptions,

            'images' =>
                $images,

            'variants' =>
                $variants,

            'seo' =>
                $seo,
        ];
    }

    /**
     * Build Shopify variants from Fullscript data.
     */
    protected function buildShopifyVariants(
        array $product
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Fullscript variant locations
        |--------------------------------------------------------------------------
        */

        $sourceVariants = [];

        if (
            isset($product['variants']) &&
            is_array($product['variants'])
        ) {
            $sourceVariants =
                $product['variants'];
        }

        /*
        |--------------------------------------------------------------------------
        | Some responses may contain variant nodes
        |--------------------------------------------------------------------------
        */

        if (
            isset($product['variants']['nodes']) &&
            is_array($product['variants']['nodes'])
        ) {
            $sourceVariants =
                $product['variants']['nodes'];
        }

        /*
        |--------------------------------------------------------------------------
        | If no variants are returned, use primary variant
        |--------------------------------------------------------------------------
        */

        if (
            empty($sourceVariants) &&
            isset($product['primary_variant']) &&
            is_array($product['primary_variant'])
        ) {
            $sourceVariants = [
                $product['primary_variant'],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        if (empty($sourceVariants)) {
            $sourceVariants = [
                $product,
            ];
        }

        $variants = [];

        foreach ($sourceVariants as $variant) {
            if (!is_array($variant)) {
                continue;
            }

            $sku =
                $variant['sku']
                ?? $variant['SKU']
                ?? '';

            $sku = trim(
                (string) $sku
            );

            if ($sku === '') {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Price
            |--------------------------------------------------------------------------
            */

            $price =
                $variant['price']
                ?? $variant['msrp']
                ?? $variant['retail_price']
                ?? '0.00';

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */

            $quantity =
                $variant['quantity']
                ?? $variant['inventory_quantity']
                ?? 0;

            /*
            |--------------------------------------------------------------------------
            | Barcode / UPC
            |--------------------------------------------------------------------------
            */

            $barcode =
                $variant['barcode']
                ?? $variant['upc']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Variant option
            |--------------------------------------------------------------------------
            */

            $optionValue =
                $variant['option_value']
                ?? $variant['name']
                ?? 'Default';

            $optionName =
                $variant['option_name']
                ?? 'Size';

            $variants[] = [
                'sku' =>
                    $sku,

                'price' =>
                    (string) $price,

                'quantity' =>
                    (int) $quantity,

                'barcode' =>
                    $barcode
                        ? (string) $barcode
                        : null,

                'option_name' =>
                    (string) $optionName,

                'option_value' =>
                    (string) $optionValue,

                'compare_at_price' =>
                    $variant['compare_at_price']
                    ?? null,

                'cost' =>
                    $variant['cost']
                    ?? null,
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
        if (empty($variants)) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Collect option values
        |--------------------------------------------------------------------------
        */

        $options = [];

        foreach ($variants as $variant) {
            $name =
                $variant['option_name']
                ?? 'Size';

            $value =
                $variant['option_value']
                ?? 'Default';

            if (!isset($options[$name])) {
                $options[$name] = [];
            }

            $options[$name][] =
                (string) $value;
        }

        /*
        |--------------------------------------------------------------------------
        | Remove duplicates
        |--------------------------------------------------------------------------
        */

        foreach ($options as $name => $values) {
            $options[$name] =
                array_values(
                    array_unique(
                        $values
                    )
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Shopify productOptions format
        |--------------------------------------------------------------------------
        */

        $result = [];

        foreach ($options as $name => $values) {
            $result[] = [
                'name' =>
                    $name,

                'values' =>
                    $values,
            ];
        }

        return $result;
    }

    /**
     * Build Shopify images.
     */
    protected function buildShopifyImages(
        array $product,
        string $title
    ): array {
        $images = [];

        /*
        |--------------------------------------------------------------------------
        | Fullscript images
        |--------------------------------------------------------------------------
        */

        if (
            isset($product['images']) &&
            is_array($product['images'])
        ) {
            foreach ($product['images'] as $image) {
                if (is_string($image)) {
                    $url = $image;

                    $alt = $title;
                } elseif (is_array($image)) {
                    $url =
                        $image['url']
                        ?? $image['src']
                        ?? $image['image_url']
                        ?? null;

                    $alt =
                        $image['alt']
                        ?? $title;
                } else {
                    continue;
                }

                if (!$url) {
                    continue;
                }

                $images[] = [
                    'url' =>
                        $url,

                    'alt' =>
                        $alt,
                ];
            }
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
                    $variant['image_url_large']
                    ?? $variant['image_url_medium']
                    ?? $variant['image_url_small']
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

        return $images;
    }

    /**
     * Get primary SKU from a Fullscript product.
     */
    protected function getPrimarySku(
        array $product
    ): string {
        /*
        |--------------------------------------------------------------------------
        | Primary variant
        |--------------------------------------------------------------------------
        */

        if (
            isset($product['primary_variant']) &&
            is_array($product['primary_variant'])
        ) {
            $sku =
                $product['primary_variant']['sku']
                ?? '';

            if (trim((string) $sku) !== '') {
                return trim(
                    (string) $sku
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Direct SKU
        |--------------------------------------------------------------------------
        */

        return trim(
            (string) (
                $product['sku']
                ?? $product['SKU']
                ?? ''
            )
        );
    }

    /**
     * Check whether a Fullscript SKU exists in Shopify.
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
                $this->shopify->findProductBySku(
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
                $variant['product']['id']
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

    /**
     * Convert Fullscript availability into display text.
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
            return Carbon::parse(
                $date
            )->format(
                'M d, Y h:i A'
            );
        } catch (Throwable) {
            return (string) $date;
        }
    }
}