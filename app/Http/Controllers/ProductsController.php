<?php



namespace App\Http\Controllers;



use App\Services\Fullscript\FullscriptProductService;

use App\Services\ProductSync\ProductTransformer;

use App\Services\Shopify\ShopifyProductService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Pagination\LengthAwarePaginator;

use Throwable;



class ProductsController extends Controller

{

    public function __construct(

        protected FullscriptProductService $fullscript,

        protected ShopifyProductService $shopify,

        protected ProductTransformer $productTransformer,

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



        /*

        |--------------------------------------------------------------------------

        | Variables

        |--------------------------------------------------------------------------

        */



        $products = [];



        $total = 0;



        $lastPage = 1;



        $error = null;



        /*

        |--------------------------------------------------------------------------

        | Load Fullscript products

        |--------------------------------------------------------------------------

        |

        | NO FILTER:

        |

        |     /catalog/products

        |

        | FILTERED:

        |

        |     /catalog/search/products

        |

        */



        try {



            if (

                $brand !== ''

                || $search !== ''

            ) {



                /*

                |--------------------------------------------------------------------------

                | Resolve brand name to Fullscript brand ID

                |--------------------------------------------------------------------------

                */



                $brandId = null;



                if ($brand !== '') {



                    $brandId =

                        $this->fullscript

                            ->findBrandIdByName(

                                $brand

                            );



                    if (!$brandId) {



                        throw new \RuntimeException(

                            "Fullscript brand not found: {$brand}"

                        );

                    }



                    \Log::info(

                        'FULLSCRIPT BRAND ID RESOLVED',

                        [

                            'brand_name' =>

                                $brand,



                            'brand_id' =>

                                $brandId,



                            'page' =>

                                $page,



                            'per_page' =>

                                $perPage,

                        ]

                    );

                }



                /*

                |--------------------------------------------------------------------------

                | Filtered search

                |--------------------------------------------------------------------------

                */



                $response =

                    $this->fullscript

                        ->searchProducts(

                            $brandId,



                            $search !== ''

                                ? $search

                                : null,



                            $page,

                            $perPage

                        );



            } else {



                /*

                |--------------------------------------------------------------------------

                | Default Products page

                |--------------------------------------------------------------------------

                |

                | When the user simply clicks Products,

                | load the Fullscript catalog immediately.

                |

                */



                $response =

                    $this->fullscript

                        ->getProducts(

                            $page,

                            $perPage

                        );

            }



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

                    $meta['total_count']

                    ?? $meta['total']

                    ?? $response['total_count']

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

            | If total count is unavailable

            |--------------------------------------------------------------------------

            */



            if (

                $total <= 0

                && $lastPage > 1

            ) {

                $total =

                    $lastPage

                    * $perPage;

            }



            /*

            |--------------------------------------------------------------------------

            | Normalize products

            |--------------------------------------------------------------------------

            */



            $seen = [];



            foreach (

                $rawProducts

                as $rawProduct

            ) {



                /*

                |--------------------------------------------------------------------------

                | Handle JSON:API attributes

                |--------------------------------------------------------------------------

                */



                if (

                    isset(

                        $rawProduct['attributes']

                    )

                    && is_array(

                        $rawProduct['attributes']

                    )

                ) {



                    $product =

                        array_merge(

                            [

                                'id' =>

                                    $rawProduct['id']

                                    ?? null,

                            ],

                            $rawProduct[

                                'attributes'

                            ]

                        );



                } else {



                    $product =

                        $rawProduct;

                }



                if (!is_array($product)) {

                    continue;

                }



                /*

                |--------------------------------------------------------------------------

                | Normalize

                |--------------------------------------------------------------------------

                */



                $normalized =

                    $this->normalizeProduct(

                        $product

                    );



                /*

                |--------------------------------------------------------------------------

                | Exact brand verification

                |--------------------------------------------------------------------------

                |

                | Keep this as a safety check even though

                | the Fullscript API is now receiving brand_id.

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

                | Product / SKU verification

                |--------------------------------------------------------------------------

                */



                // if ( $search !== '' && stripos( $normalized['title'], $search  ) === false && stripos(  $normalized['sku'], $search ) === false ) {
                //     continue;
                // }

                /*
                |--------------------------------------------------------------------------
                | SKU search
                |--------------------------------------------------------------------------
                |
                | Search only by SKU.
                | Product title is intentionally not searched.
                |
                */

                if (
                    $search !== ''
                    && stripos(
                        $normalized['sku'],
                        $search
                    ) === false
                ) {
                    continue;
                }

                /*

                |--------------------------------------------------------------------------

                | Deduplicate

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

                    && isset(

                        $seen[$uniqueKey]

                    )

                ) {

                    continue;

                }



                $seen[$uniqueKey] = true;



                /*

                |--------------------------------------------------------------------------

                | Add normalized product

                |--------------------------------------------------------------------------

                |

                | Shopify status is resolved in one batch after the Fullscript

                | products have been normalized. This avoids one Shopify API

                | request per product and prevents the listing page from

                | becoming slow or timing out.

                */

                $products[] =

                    $normalized;

            }

            /*
            |--------------------------------------------------------------------------
            | Shopify status
            |--------------------------------------------------------------------------
            |
            | Check all SKUs on the current page in a single batched Shopify
            | lookup. The result is rendered directly in the product listing.
            |
            */

            $shopifySkus = [];

            foreach ($products as $product) {
                $sku = trim(
                    (string) (
                        $product['sku'] ?? ''
                    )
                );

                if ($sku !== '') {
                    $shopifySkus[] = $sku;
                }
            }

            $shopifyMatches = [];

            if (!empty($shopifySkus)) {
                try {
                    $shopifyMatches =
                        $this->shopify->findProductsBySkus(
                            $shopifySkus
                        );
                } catch (Throwable $e) {
                    report($e);
                    $shopifyMatches = null;
                }
            }

            foreach ($products as $index => $product) {
                $sku = trim(
                    (string) (
                        $product['sku'] ?? ''
                    )
                );

                if ($sku === '') {
                    $products[$index]['shopify_status'] = 'Not Found';
                    $products[$index]['shopify_status_text'] = 'No SKU';
                    $products[$index]['shopify_product_id'] = null;
                    $products[$index]['action'] = 'push';
                    continue;
                }

                if ($shopifyMatches === null) {
                    $products[$index]['shopify_status'] = 'Error';
                    $products[$index]['shopify_status_text'] = 'Unable to check';
                    $products[$index]['shopify_product_id'] = null;
                    $products[$index]['action'] = 'push';
                    continue;
                }

                $variant = $shopifyMatches[$sku] ?? null;

                if ($variant) {
                    $products[$index]['shopify_status'] = 'Exists';
                    $products[$index]['shopify_status_text'] = 'Will update';
                    $products[$index]['shopify_product_id'] =
                        $variant['product']['id'] ?? null;
                    $products[$index]['action'] = 'update';
                } else {
                    $products[$index]['shopify_status'] = 'Not Found';
                    $products[$index]['shopify_status_text'] = 'Will create';
                    $products[$index]['shopify_product_id'] = null;
                    $products[$index]['action'] = 'push';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Fallback total

            |--------------------------------------------------------------------------

            */



            if ($total <= 0) {



                $total =

                    (($page - 1) * $perPage)

                    + count($products);

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



            /*

            |--------------------------------------------------------------------------

            | Get detailed Fullscript product

            |--------------------------------------------------------------------------

            |

            | The single-product endpoint does NOT return the product image.

            |

            */



            $fullscriptResponse =

                $this->fullscript->getProduct(

                    $productId

                );



            $fullscriptProduct =

                $fullscriptResponse['product']

                ?? $fullscriptResponse['data']

                ?? $fullscriptResponse;





            /*

            |--------------------------------------------------------------------------

            | Get image from product listing

            |--------------------------------------------------------------------------

            |

            | The listing/search response contains:

            |

            | primary_variant.image_url_large

            |

            */



            $imageUrlLarge =

                trim(

                    (string) $request->input(

                        'image_url_large',

                        ''

                    )

                );





            /*

            |--------------------------------------------------------------------------

            | Add listing image to detailed product

            |--------------------------------------------------------------------------

            */



            if ($imageUrlLarge !== '') {



                if (

                    !isset(

                        $fullscriptProduct[

                            'primary_variant'

                        ]

                    )

                    || !is_array(

                        $fullscriptProduct[

                            'primary_variant'

                        ]

                    )

                ) {



                    $fullscriptProduct[

                        'primary_variant'

                    ] = [];



                }



                $fullscriptProduct[

                    'primary_variant'

                ][

                    'image_url_large'

                ] = $imageUrlLarge;

            }





            /*

            |--------------------------------------------------------------------------

            | Log image

            |--------------------------------------------------------------------------

            */



            \Log::info(

                'FULLSCRIPT PUSH IMAGE',

                [

                    'product_id' =>

                        $productId,



                    'image_url_large' =>

                        $fullscriptProduct[

                            'primary_variant'

                        ]['image_url_large']

                        ?? null,

                ]

            );





            /*

            |--------------------------------------------------------------------------

            | Transform

            |--------------------------------------------------------------------------

            */



            $product =

                $this->productTransformer->transform(

                    $fullscriptProduct

                );





            /*

            |--------------------------------------------------------------------------

            | Find existing Shopify product by SKU

            |--------------------------------------------------------------------------

            */



            $shopifyProductId =

                $request->input(

                    'shopify_product_id'

                );



            if (

                blank(

                    $shopifyProductId

                )

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





            /*

            |--------------------------------------------------------------------------

            | Create / Update Shopify product

            |--------------------------------------------------------------------------

            */



            $result =

                $this->shopify->createOrUpdate(

                    $product,

                    $shopifyProductId

                );





            return response()->json(

                [

                    'success' =>

                        true,



                    'message' =>

                        $shopifyProductId

                            ? 'Product updated successfully.'

                            : 'Product created successfully.',



                    'data' =>

                        $result,

                ]

            );



        } catch (Throwable $e) {



            report($e);



            return response()->json(

                [

                    'success' =>

                        false,



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



        /*
        |--------------------------------------------------------------------------
        | Images from the product listing
        |--------------------------------------------------------------------------
        |
        | The Fullscript detail endpoint does not return the product image.
        | The listing does, so the Blade sends the image URL for each selected
        | product.
        |
        */

        $imageUrls =

            $request->input(

                'image_urls',

                []

            );



        if (!is_array($imageUrls)) {
            $imageUrls = [];
        }



        if (!is_array($productIds)) {



            return response()->json(

                [

                    'success' =>

                        false,



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

                    'success' =>

                        false,



                    'message' =>

                        'Please select at least one product.',

                ],

                422

            );

        }



        $results = [];



        foreach ($productIds as $productId) {



            try {



                $fullscriptResponse =

                    $this->fullscript->getProduct(

                        $productId

                    );



                $fullscriptProduct =

                    $fullscriptResponse['product']

                    ?? $fullscriptResponse['data']

                    ?? $fullscriptResponse;



                /*
                |--------------------------------------------------------------------------
                | Restore image from product listing
                |--------------------------------------------------------------------------
                */

                $imageUrlLarge =

                    trim(

                        (string) (

                            $imageUrls[$productId]

                            ?? ''

                        )

                    );



                if ($imageUrlLarge !== '') {

                    if (
                        !isset(
                            $fullscriptProduct['primary_variant']
                        )
                        || !is_array(
                            $fullscriptProduct['primary_variant']
                        )
                    ) {
                        $fullscriptProduct['primary_variant'] = [];
                    }

                    $fullscriptProduct['primary_variant']['image_url_large'] =
                        $imageUrlLarge;
                }



                \Log::info(

                    'FULLSCRIPT PUSH SELECTED IMAGE',

                    [

                        'product_id' => $productId,

                        'image_url_large' => $imageUrlLarge ?: null,

                    ]

                );



                $product =

                    $this->productTransformer->transform(

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

     * Normalize Fullscript product for display.

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



        /*

        |--------------------------------------------------------------------------

        | Primary variant SKU

        |--------------------------------------------------------------------------

        */



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



        /*

        |--------------------------------------------------------------------------

        | Variants

        |--------------------------------------------------------------------------

        */



        if (

            $sku === ''

            && isset(

                $product['variants']

            )

            && is_array(

                $product['variants']

            )

        ) {



            foreach (

                $product['variants']

                as $variant

            ) {



                if (

                    is_array($variant)

                    && !empty(

                        $variant['sku']

                    )

                ) {



                    $sku =

                        $variant['sku'];



                    break;

                }

            }

        }



        /*

        |--------------------------------------------------------------------------

        | Availability

        |--------------------------------------------------------------------------

        */



        $availability =

            $product['availability']

            ?? $product['availability_status']

            ?? $product['status']

            ?? '';



        /*

        |--------------------------------------------------------------------------

        | Image

        |--------------------------------------------------------------------------

        */



        $image = null;



        if (

            isset(

                $product['image']

            )

            && is_string(

                $product['image']

            )

        ) {



            $image =

                $product['image'];



        } elseif (

            isset(

                $product['image_url']

            )

        ) {



            $image =

                $product['image_url'];



        } elseif (

            isset(

                $product[

                    'primary_variant'

                ]

            )

            && is_array(

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



            if (

                is_string(

                    $firstImage

                )

            ) {



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

                trim($brand),



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

     * Normalize detailed Fullscript product for Shopify.

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



        if (

            is_array(

                $brandValue

            )

        ) {



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



        /*

        |--------------------------------------------------------------------------

        | Variants

        |--------------------------------------------------------------------------

        */



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



        foreach (

            $variants as $variant

        ) {



            if (

                !is_array($variant)

            ) {

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

                    $variant[

                        'option_values'

                    ]

                    ?? [],



                'inventory_quantity' =>

                    0,

            ];

        }



        /*

        |--------------------------------------------------------------------------

        | Fallback primary variant

        |--------------------------------------------------------------------------

        */



        if (

            empty(

                $shopifyVariants

            )

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

                $product[

                    'product_type'

                ]

                ?? 'Vitamins & Supplements',



            'status' =>

                'ACTIVE',



            'gift_card' =>

                false,



            'tags' =>

                $product['tags']

                ?? [],



            'product_options' =>

                $product[

                    'product_options'

                ]

                ?? [],



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

     * Build Shopify images.

     */

    protected function buildShopifyImages(

        array $product

    ): array {

        $images =

            $product['images']

            ?? [];



        if (!is_array($images)) {

            $images = [];

        }



        $result = [];



        foreach (

            $images as $image

        ) {



            if (

                is_string($image)

            ) {



                $result[] = [

                    'url' =>

                        $image,

                ];



                continue;

            }



            if (

                !is_array($image)

            ) {

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



        /*

        |--------------------------------------------------------------------------

        | Primary variant image fallback

        |--------------------------------------------------------------------------

        */



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



        if (

            is_array(

                $variants

            )

        ) {



            foreach (

                $variants as $variant

            ) {



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

     * Format availability.

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

     * Format date.

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

     * Check whether a SKU already exists in Shopify.

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


    public function show(
            Request $request,
            string $productId
        ) {
            try {
                $response = $this->fullscript->getProduct($productId);

                $product =
                    $response['product']
                    ?? $response['data']
                    ?? $response;

                if (!is_array($product)) {
                    abort(404, 'Product was not found.');
                }

                // Get image passed from product listing
                $imageUrlLarge = trim(
                    (string) $request->query(
                        'image_url_large',
                        ''
                    )
                );

                if ($imageUrlLarge !== '') {
                    if (
                        !isset($product['primary_variant'])
                        || !is_array($product['primary_variant'])
                    ) {
                        $product['primary_variant'] = [];
                    }

                    $product['primary_variant']['image_url_large'] =
                        $imageUrlLarge;
                }

                $brand =
                    $product['brand']['name']
                    ?? $product['brand_name']
                    ?? $product['vendor']
                    ?? 'Fullscript';

                if (is_array($brand)) {
                    $brand = $brand['name'] ?? 'Fullscript';
                }

                $variants = $product['variants'] ?? [];

                if (isset($variants['nodes'])) {
                    $variants = $variants['nodes'];
                }

                if (!is_array($variants) || empty($variants)) {
                    $primary = $product['primary_variant'] ?? [];
                    $variants = !empty($primary)
                        ? [$primary]
                        : [];
                }

                $primarySku =
                    $this->getPrimarySku($product);

                $shopifyInfo =
                    $this->getShopifyStatus($primarySku);

                return view('products.show', [
                    'product' => $product,
                    'brand' => (string) $brand,
                    'variants' => $variants,
                    'primarySku' => $primarySku,
                    'shopifyInfo' => $shopifyInfo,
                    'imageUrlLarge' => $imageUrlLarge
                        ?: ($product['primary_variant']['image_url_large'] ?? null),
                    'error' => null,
                ]);

            } catch (Throwable $e) {

                report($e);

                return view('products.show', [
                    'product' => null,
                    'brand' => 'Fullscript',
                    'variants' => [],
                    'primarySku' => '',
                    'shopifyInfo' => [
                        'status' => 'Error',
                        'text' => 'Unable to check Shopify',
                        'product_id' => null,
                        'action' => 'retry',
                    ],
                    'imageUrlLarge' => null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

}