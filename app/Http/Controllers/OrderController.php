<?php

namespace App\Http\Controllers;

use App\Models\ShopifyToken;
use App\Services\Shopify\ShopifyFulfillmentService;
use App\Services\Shopify\ShopifyGraphQLService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        protected ShopifyGraphQLService $shopify,
        protected ShopifyFulfillmentService $fulfillmentService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Orders List
    |--------------------------------------------------------------------------
    |
    | Shows only orders that have fulfillment orders assigned to our
    | registered FSWarehouse fulfillment location.
    |
    */

    public function index(): View
    {
        try {
            $shopifyToken = $this->getShopifyToken();

            $locationId = $shopifyToken->fulfillment_location_id;

            if (blank($locationId)) {
                throw new RuntimeException(
                    'Shopify fulfillment location ID is not configured.'
                );
            }

            $query = <<<'GRAPHQL'
            query GetAssignedFulfillmentOrders(
                $first: Int!
                $after: String
                $locationIds: [ID!]
            ) {
                assignedFulfillmentOrders(
                    first: $first
                    after: $after
                    locationIds: $locationIds
                    sortKey: UPDATED_AT
                    reverse: true
                ) {
                    nodes {
                        id
                        status
                        requestStatus
                        createdAt
                        updatedAt

                        assignedLocation {
                            location {
                                id
                                name
                            }
                        }

                        order {
                            id
                            name
                            createdAt
                            displayFinancialStatus
                            displayFulfillmentStatus

                            totalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }

                            customer {
                                firstName
                                lastName
                                email
                            }

                            fulfillments {
                                id
                                status
                                createdAt

                                location {
                                    id
                                    name
                                }

                                service {
                                    id
                                    handle
                                    serviceName
                                }

                                trackingInfo {
                                    company
                                    number
                                    url
                                }
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

            $data = $this->shopify->executeWithCredentials(
                $shopifyToken->shop_domain,
                $shopifyToken->access_token,
                $query,
                [
                    'first' => 100,
                    'after' => null,
                    'locationIds' => [
                        $locationId,
                    ],
                ]
            );

            $assignedFulfillmentOrders =
                $data['assignedFulfillmentOrders']['nodes'] ?? [];

            $pageInfo =
                $data['assignedFulfillmentOrders']['pageInfo'] ?? [];

            $orders = [];

            foreach ($assignedFulfillmentOrders as $fulfillmentOrder) {
                $order = $fulfillmentOrder['order'] ?? null;

                if (! $order || empty($order['id'])) {
                    continue;
                }

                $orderId = $order['id'];

                if (! isset($orders[$orderId])) {
                    $orders[$orderId] = $order;
                }
            }

            $orders = array_values($orders);

            return view('orders.index', [
                'orders' => $orders,
                'pageInfo' => $pageInfo,
                'error' => null,
            ]);

        } catch (Throwable $e) {
            report($e);

            return view('orders.index', [
                'orders' => [],
                'pageInfo' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Order Details
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We do NOT use order.fulfillments directly.
    |
    | We first get the order's fulfillmentOrders and keep only the
    | fulfillment order assigned to our registered FSWarehouse location.
    |
    | This allows an order to contain:
    |
    |   FSWarehouse       -> shown
    |   Headquarters      -> hidden
    |   Other locations   -> hidden
    |
    */

    public function show(string $orderId): View
    {
        $shopifyOrderId = 'gid://shopify/Order/' . $orderId;

        try {
            $shopifyToken = $this->getShopifyToken();

            $locationId = $shopifyToken->fulfillment_location_id;

            if (blank($locationId)) {
                throw new RuntimeException(
                    'Shopify fulfillment location ID is not configured.'
                );
            }

            $query = <<<'GRAPHQL'
            query GetOrder($id: ID!) {
                order(id: $id) {
                    id
                    name
                    createdAt

                    displayFinancialStatus
                    displayFulfillmentStatus

                    totalPriceSet {
                        shopMoney {
                            amount
                            currencyCode
                        }
                    }

                    customer {
                        firstName
                        lastName
                        email
                    }

                    fulfillmentOrders(first: 100) {
                        nodes {
                            id
                            status
                            requestStatus
                            createdAt
                            updatedAt

                            assignedLocation {
                                location {
                                    id
                                    name
                                }
                            }

                            lineItems(first: 100) {
                                nodes {
                                    id
                                    quantity
                                    remainingQuantity

                                    lineItem {
                                        id
                                        name
                                        sku
                                        quantity
                                    }
                                }
                            }

                            fulfillments(first: 50) {
                                nodes {
                                    id
                                    status
                                    createdAt

                                    trackingInfo {
                                        company
                                        number
                                        url
                                    }
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $data = $this->shopify->executeWithCredentials(
                $shopifyToken->shop_domain,
                $shopifyToken->access_token,
                $query,
                [
                    'id' => $shopifyOrderId,
                ]
            );

            $order = $data['order'] ?? null;

            if (! $order) {
                abort(404, 'Shopify order not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | Keep ONLY FSWarehouse fulfillment orders
            |--------------------------------------------------------------------------
            */

            $fsFulfillmentOrders = collect(
                $order['fulfillmentOrders']['nodes'] ?? []
            )
                ->filter(function (array $fulfillmentOrder) use ($locationId) {
                    return ($fulfillmentOrder['assignedLocation']['location']['id'] ?? null)
                        === $locationId;
                })
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | If the order exists but does not contain an FSWarehouse
            | fulfillment order, don't display other locations.
            |--------------------------------------------------------------------------
            */

            if (empty($fsFulfillmentOrders)) {
                abort(
                    404,
                    'No FSWarehouse fulfillment found for this order.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Build FSWarehouse-only line items
            |--------------------------------------------------------------------------
            */

            $fsLineItems = [];

            foreach ($fsFulfillmentOrders as $fulfillmentOrder) {
                foreach (
                    $fulfillmentOrder['lineItems']['nodes'] ?? []
                    as $lineItem
                ) {
                    $fsLineItems[] = [
                        'id' => $lineItem['id'] ?? null,
                        'quantity' => $lineItem['quantity'] ?? 0,
                        'remainingQuantity' => $lineItem['remainingQuantity'] ?? 0,

                        'name' =>
                            $lineItem['lineItem']['name'] ?? '-',

                        'sku' =>
                            $lineItem['lineItem']['sku'] ?? '-',
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Build FSWarehouse-only fulfillments
            |--------------------------------------------------------------------------
            */

            $fsFulfillments = [];

            foreach ($fsFulfillmentOrders as $fulfillmentOrder) {
                foreach (
                    $fulfillmentOrder['fulfillments']['nodes'] ?? []
                    as $fulfillment
                ) {
                    $fsFulfillments[] = $fulfillment;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Add our filtered data to the order
            |--------------------------------------------------------------------------
            */

            $order['fsFulfillmentOrders'] = $fsFulfillmentOrders;

            $order['fsLineItems'] = $fsLineItems;

            $order['fsFulfillments'] = $fsFulfillments;

            return view('orders.show', [
                'order' => $order,
            ]);

        } catch (Throwable $e) {
            report($e);

            return view('orders.show', [
                'order' => null,
                'orderId' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Add / Update Tracking
    |--------------------------------------------------------------------------
    |
    | The same method handles both:
    |
    |   No tracking -> Add tracking
    |   Existing tracking -> Update tracking
    |
    */

    public function updateTracking(
        Request $request,
        string $orderId,
        string $fulfillmentId
    ): RedirectResponse {
        $validated = $request->validate([
            'company' => [
                'required',
                'string',
                'max:255',
                'in:' . implode(
                    ',',
                    config('shopify.tracking_carriers', [])
                ),
            ],

            'tracking_number' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $shopifyFulfillmentId =
            'gid://shopify/Fulfillment/' . $fulfillmentId;

        try {
            $shopifyToken = $this->getShopifyToken();

            /*
            |--------------------------------------------------------------------------
            | Make sure this fulfillment belongs to FSWarehouse
            |--------------------------------------------------------------------------
            |
            | This protects the endpoint even if somebody manually changes
            | the fulfillment ID in the browser.
            |
            */

            $this->ensureFulfillmentBelongsToFswWarehouse(
                $shopifyToken,
                $shopifyFulfillmentId
            );

            /*
            |--------------------------------------------------------------------------
            | Add / Update tracking
            |--------------------------------------------------------------------------
            */

            $this->fulfillmentService->updateTracking(
                $shopifyFulfillmentId,
                $validated['tracking_number'],
                $validated['company'],
                null,
                $shopifyToken->id
            );

            return redirect()
                ->route('orders.show', [
                    'orderId' => $orderId,
                ])
                ->with(
                    'success',
                    'Tracking information updated successfully.'
                );

        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('orders.show', [
                    'orderId' => $orderId,
                ])
                ->withInput()
                ->with(
                    'error',
                    'Unable to update tracking information: ' .
                    $e->getMessage()
                );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Get Current Shopify Token
    |--------------------------------------------------------------------------
    */

    protected function getShopifyToken(): ShopifyToken
    {
        $shopifyToken = ShopifyToken::query()
            ->where(
                'shop_domain',
                config('shopify.store_domain')
            )
            ->whereNotNull('access_token')
            ->first();

        if (! $shopifyToken) {
            throw new RuntimeException(
                'Shopify token not found.'
            );
        }

        return $shopifyToken;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Fulfillment Belongs to FSWarehouse
    |--------------------------------------------------------------------------
    |
    | We check the fulfillment's associated fulfillment orders.
    |
    */

    protected function ensureFulfillmentBelongsToFswWarehouse(
        ShopifyToken $shopifyToken,
        string $fulfillmentId
    ): void {
        $locationId = $shopifyToken->fulfillment_location_id;

        if (blank($locationId)) {
            throw new RuntimeException(
                'Shopify fulfillment location ID is not configured.'
            );
        }

        $query = <<<'GRAPHQL'
        query GetFulfillment($id: ID!) {
            fulfillment(id: $id) {
                id

                fulfillmentOrders(first: 50) {
                    nodes {
                        assignedLocation {
                            location {
                                id
                                name
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $data = $this->shopify->executeWithCredentials(
            $shopifyToken->shop_domain,
            $shopifyToken->access_token,
            $query,
            [
                'id' => $fulfillmentId,
            ]
        );

        $fulfillment = $data['fulfillment'] ?? null;

        if (! $fulfillment) {
            throw new RuntimeException(
                'Shopify fulfillment not found.'
            );
        }

        $belongsToFsw = false;

        foreach (
            $fulfillment['fulfillmentOrders']['nodes'] ?? []
            as $fulfillmentOrder
        ) {
            $assignedLocationId =
                $fulfillmentOrder['assignedLocation']['location']['id']
                ?? null;

            if ($assignedLocationId === $locationId) {
                $belongsToFsw = true;
                break;
            }
        }

        if (! $belongsToFsw) {
            throw new RuntimeException(
                'This fulfillment does not belong to FSWarehouse.'
            );
        }
    }
}