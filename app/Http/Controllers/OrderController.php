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
    | Show only orders that have a fulfillment order assigned to our
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

        $after = request()->query('after');

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
                    }
                }

                pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
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
                'after' => $after ?: null,
                'locationIds' => [
                    $locationId,
                ],
            ]
        );

        $connection =
            $data['assignedFulfillmentOrders'] ?? [];

        $assignedFulfillmentOrders =
            $connection['nodes'] ?? [];

        $pageInfo =
            $connection['pageInfo'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Remove duplicate Shopify orders
        |--------------------------------------------------------------------------
        */

        $orders = [];

        foreach ($assignedFulfillmentOrders as $fulfillmentOrder) {
            $order = $fulfillmentOrder['order'] ?? null;

            if (! $order || empty($order['id'])) {
                continue;
            }

            $orders[$order['id']] = $order;
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
    | We use assignedFulfillmentOrders instead of the normal
    | order.fulfillments connection.
    |
    | This ensures that only fulfillment orders assigned to the
    | registered FSWarehouse location are returned.
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

            /*
            |--------------------------------------------------------------------------
            | Get FSWarehouse assigned fulfillment orders
            |--------------------------------------------------------------------------
            */

            $query = <<<'GRAPHQL'
            query GetAssignedFulfillmentOrders(
                $first: Int!
                $locationIds: [ID!]
            ) {
                assignedFulfillmentOrders(
                    first: $first
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
                        }

                        lineItems(first: 100) {
                            nodes {
                                id
                                totalQuantity
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
            GRAPHQL;

            $data = $this->shopify->executeWithCredentials(
                $shopifyToken->shop_domain,
                $shopifyToken->access_token,
                $query,
                [
                    'first' => 100,
                    'locationIds' => [
                        $locationId,
                    ],
                ]
            );

            $assignedFulfillmentOrders =
                $data['assignedFulfillmentOrders']['nodes'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | Find only this order
            |--------------------------------------------------------------------------
            */

            $fsFulfillmentOrders = collect(
                $assignedFulfillmentOrders
            )
                ->filter(function (array $fulfillmentOrder) use ($shopifyOrderId) {
                    return (
                        $fulfillmentOrder['order']['id'] ?? null
                    ) === $shopifyOrderId;
                })
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | No FSWarehouse fulfillment order
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
            | Use the first FSWarehouse fulfillment order for order details
            |--------------------------------------------------------------------------
            */

            $firstFulfillmentOrder = $fsFulfillmentOrders[0];

            $order = $firstFulfillmentOrder['order'] ?? null;

            if (! $order) {
                abort(404, 'Shopify order not found.');
            }

            /*
            |--------------------------------------------------------------------------
            | Attach FSWarehouse-specific data
            |--------------------------------------------------------------------------
            */

            $order['fsFulfillmentOrders'] =
                $fsFulfillmentOrders;

            /*
            |--------------------------------------------------------------------------
            | Build FSWarehouse products
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

                        'quantity' =>
                            $lineItem['totalQuantity'] ?? 0,

                        'remainingQuantity' =>
                            $lineItem['remainingQuantity'] ?? 0,

                        'name' =>
                            $lineItem['lineItem']['name'] ?? '-',

                        'sku' =>
                            $lineItem['lineItem']['sku'] ?? '-',
                    ];
                }
            }

            $order['fsLineItems'] = $fsLineItems;

            /*
            |--------------------------------------------------------------------------
            | Build FSWarehouse fulfillments
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
    | The same endpoint handles both:
    |
    | 1. Adding tracking when no tracking exists.
    | 2. Updating tracking when tracking already exists.
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
            | Security check
            |--------------------------------------------------------------------------
            |
            | Make sure the fulfillment belongs to our FSWarehouse
            | fulfillment order before allowing tracking changes.
            |
            */

            $this->ensureFulfillmentBelongsToFswWarehouse(
                $shopifyToken,
                $shopifyFulfillmentId
            );

            /*
            |--------------------------------------------------------------------------
            | Add / Update Shopify Tracking
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
    | Get Shopify Token
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
    | Verify Fulfillment Belongs To FSWarehouse
    |--------------------------------------------------------------------------
    |
    | Instead of querying an arbitrary fulfillment directly, we inspect
    | the assigned fulfillment orders returned to our app.
    |
    | This keeps the check restricted to our registered FSWarehouse.
    |
    */

    protected function ensureFulfillmentBelongsToFswWarehouse(
        ShopifyToken $shopifyToken,
        string $fulfillmentId
    ): void {
        $locationId =
            $shopifyToken->fulfillment_location_id;

        if (blank($locationId)) {
            throw new RuntimeException(
                'Shopify fulfillment location ID is not configured.'
            );
        }

        $query = <<<'GRAPHQL'
        query GetAssignedFulfillmentOrders(
            $first: Int!
            $locationIds: [ID!]
        ) {
            assignedFulfillmentOrders(
                first: $first
                locationIds: $locationIds
                sortKey: UPDATED_AT
                reverse: true
            ) {
                nodes {
                    id

                    fulfillments(first: 50) {
                        nodes {
                            id
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
                'first' => 100,
                'locationIds' => [
                    $locationId,
                ],
            ]
        );

        $assignedFulfillmentOrders =
            $data['assignedFulfillmentOrders']['nodes'] ?? [];

        foreach ($assignedFulfillmentOrders as $fulfillmentOrder) {
            foreach (
                $fulfillmentOrder['fulfillments']['nodes'] ?? []
                as $fulfillment
            ) {
                if (
                    ($fulfillment['id'] ?? null)
                    === $fulfillmentId
                ) {
                    return;
                }
            }
        }

        throw new RuntimeException(
            'This fulfillment does not belong to FSWarehouse.'
        );
    }
}