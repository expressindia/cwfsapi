<?php

namespace App\Http\Controllers;

use App\Services\Fullscript\FullscriptProductService;
use App\Services\Shopify\ShopifyProductService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

        /*
        |--------------------------------------------------------------------------
        | Only allow supported page sizes
        |--------------------------------------------------------------------------
        */

        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        /*
        |--------------------------------------------------------------------------
        | Search values
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
        | Load products from Fullscript
        |--------------------------------------------------------------------------
        */

        $products = [];

        $pagination = [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => 0,
            'last_page' => 1,
        ];

        $error = null;

        try {

            $response =
                $this->fullscript->getProducts(
                    $page,
                    $perPage
                );

            /*
            |--------------------------------------------------------------------------
            | Extract product collection
            |--------------------------------------------------------------------------
            |
            | Fullscript responses can contain the products under "data".
            | We also keep this tolerant of a direct array response.
            |
            */

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
                $meta['page']
                ?? $meta['pagination']
                ?? $meta;

            $total =
                $paginationData['total']
                ?? $meta['total']
                ?? $response['total']
                ?? 0;

            $lastPage =
                $paginationData['total_pages']
                ?? $paginationData['last_page']
                ?? $meta['total_pages']
                ?? $response['total_pages']
                ?? 1;

            /*
            |--------------------------------------------------------------------------
            | If Fullscript does not provide total pages
            |--------------------------------------------------------------------------
            */

            if (
                !$lastPage &&
                $total > 0
            ) {
                $lastPage = (int) ceil(
                    $total / $perPage
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Normalize products
            |--------------------------------------------------------------------------
            */

            foreach ($rawProducts as $rawProduct) {

                /*
                |--------------------------------------------------------------------------
                | JSON:API style response
                |--------------------------------------------------------------------------
                */

                if (
                    isset($rawProduct['attributes']) &&
                    is_array($rawProduct['attributes'])
                ) {

                    $product =
                        array_merge(
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

                /*
                |--------------------------------------------------------------------------
                | Normalize the product
                |--------------------------------------------------------------------------
                */

                $normalized =
                    $this->normalizeProduct(
                        $product
                    );

                /*
                |--------------------------------------------------------------------------
                | Local search fallback
                |--------------------------------------------------------------------------
                |
                | This applies to the products returned by the current API page.
                | Full catalog server-side search will be connected after we
                | confirm Fullscript's exact Granular Search parameters.
                |
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

                $shopifyInfo =
                    $this->getShopifyStatus(
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

                $products[] =
                    $normalized;
            }

            /*
            |--------------------------------------------------------------------------
            | Better fallback when API doesn't return total
            |--------------------------------------------------------------------------
            */

            if (!$total) {

                if (count($rawProducts) === $perPage) {

                    /*
                    * We know another page may exist.
                    * Don't pretend we know the total catalog count.
                    */

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

            if (!$lastPage) {

                $lastPage =
                    max(
                        1,
                        (int) ceil(
                            $total / $perPage
                        )
                    );

            }

            /*
            |--------------------------------------------------------------------------
            | Prevent invalid page
            |--------------------------------------------------------------------------
            */

            $lastPage =
                max(
                    1,
                    (int) $lastPage
                );

        } catch (Throwable $e) {

            report($e);

            $error =
                $e->getMessage();

        }

        /*
        |--------------------------------------------------------------------------
        | Pagination object
        |--------------------------------------------------------------------------
        |
        | This allows the Blade page to use normal Laravel pagination.
        |
        */

        $paginator =
            new LengthAwarePaginator(
                $products,
                $total ?? count($products),
                $perPage,
                $page,
                [
                    'path' =>
                        route('products.index'),

                    'query' =>
                        $request->except('page'),
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Brand quick filters
        |--------------------------------------------------------------------------
        |
        | We keep the approved brands for the UI for now.
        | Later these can come from Fullscript's catalog metadata.
        |
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
                'products' =>
                    $paginator,

                'brands' =>
                    $brands,

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
     * Normalize a Fullscript product.
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

        $brand =
            $product['brand']
            ?? $product['brand_name']
            ?? $product['vendor']
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | SKU
        |--------------------------------------------------------------------------
        */

        $sku =
            $product['sku']
            ?? $product['SKU']
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | Availability
        |--------------------------------------------------------------------------
        */

        $availability =
            $product['availability']
            ?? $product['availability_status']
            ?? $product['status']
            ?? 'Unknown';

        /*
        |--------------------------------------------------------------------------
        | Image
        |--------------------------------------------------------------------------
        */

        $image = null;

        if (
            isset($product['image']) &&
            is_string($product['image'])
        ) {

            $image =
                $product['image'];

        } elseif (
            isset($product['image_url'])
        ) {

            $image =
                $product['image_url'];

        } elseif (
            isset($product['images']) &&
            is_array($product['images'])
        ) {

            $firstImage =
                $product['images'][0]
                ?? null;

            if (is_string($firstImage)) {

                $image =
                    $firstImage;

            } elseif (
                is_array($firstImage)
            ) {

                $image =
                    $firstImage['url']
                    ?? $firstImage['src']
                    ?? null;

            }

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

        /*
        |--------------------------------------------------------------------------
        | Return normalized structure
        |--------------------------------------------------------------------------
        */

        return [

            'id' =>
                $id,

            'title' =>
                (string) $title,

            'brand' =>
                (string) $brand,

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
     * Convert Fullscript availability into a display value.
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
            'in stock'
                => 'In Stock',

            'backordered',
            'backorder',
            'backordered '
                => 'Backordered',

            'out_of_stock',
            'out-of-stock',
            'out of stock'
                => 'Out of Stock',

            'discontinued'
                => 'Discontinued',

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
}