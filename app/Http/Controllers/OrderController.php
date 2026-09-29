<?php

namespace App\Http\Controllers;

use App\Models\ShopifyToken;
use App\Services\Shopify\ShopifyFulfillmentService;
use App\Services\Shopify\ShopifyGraphQLService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        protected ShopifyGraphQLService $shopify,
        protected ShopifyFulfillmentService $fulfillmentService
    ) {
    }

    /**
     * Display FSWarehouse orders.
     *
     * Only orders assigned to the registered FSWarehouse location
     * are displayed.
     */
    public function index(Request $request)
    {
        $shopifyToken = $this->getShopifyToken();

        $locationId = $shopifyToken->fulfillment_location_id;

        if (blank($locationId)) {
            return view('orders.index', [
                'orders' => [],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'hasPreviousPage' => false,
                    'startCursor' => null,
                    'endCursor' => null,
                ],
                'nextUrl' => null,
                'previousUrl' => null,
                'search' => $request->query('search'),
                'error' => 'FSWarehouse fulfillment location is not configured.',
            ]);
        }

        $search = trim((string) $request->query('search', ''));

        /*
         * Search by order ID/order number.
         */
        if ($search !== '') {
            return $this->searchOrder(
                $shopifyToken,
                $locationId,
                $search
            );
        }

        try {
            $after = $request->query('after');
            $before = $request->query('before');

            /*
             * Shopify connection pagination:
             *
             * Next:
             *   first = 100
             *   after = endCursor
             *
             * Previous:
             *   last = 100
             *   before = startCursor
             */
            if ($before) {
                $first = null;
                $last = 100;
                $afterCursor = null;
                $beforeCursor = $before;
            } else {
                $first = 100;
                $last = null;
                $afterCursor = $after;
                $beforeCursor = null;
            }

            $query = <<<'GRAPHQL'
query GetAssignedFulfillmentOrders(
    $first: Int
    $last: Int
    $after: String
    $before: String
    $locationIds: [ID!]
) {
    assignedFulfillmentOrders(
        first: $first
        last: $last
        after: $after
        before: $before
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

            $data = $this->executeForStore(
                $shopifyToken,
                $query,
                [
                    'first' => $first,
                    'last' => $last,
                    'after' => $afterCursor,
                    'before' => $beforeCursor,
                    'locationIds' => [$locationId],
                ]
            );

            $connection = $data['assignedFulfillmentOrders'] ?? [];

            $orders = [];

            foreach (($connection['nodes'] ?? []) as $fulfillmentOrder) {
                $order = $fulfillmentOrder['order'] ?? null;

                if (!$order) {
                    continue;
                }

                /*
                 * Since assignedFulfillmentOrders is already filtered by
                 * locationIds, these orders belong to FSWarehouse.
                 */
                $order['fsFulfillmentOrder'] = $fulfillmentOrder;

                $orders[] = $order;
            }

            $pageInfo = $connection['pageInfo'] ?? [
                'hasNextPage' => false,
                'hasPreviousPage' => false,
                'startCursor' => null,
                'endCursor' => null,
            ];

            $nextUrl = null;
            $previousUrl = null;

            if (!empty($pageInfo['hasNextPage']) && !empty($pageInfo['endCursor'])) {
                $nextUrl = route('orders.index', [
                    'after' => $pageInfo['endCursor'],
                ]);
            }

            if (!empty($pageInfo['hasPreviousPage']) && !empty($pageInfo['startCursor'])) {
                $previousUrl = route('orders.index', [
                    'before' => $pageInfo['startCursor'],
                ]);
            }

            return view('orders.index', [
                'orders' => $orders,
                'pageInfo' => $pageInfo,
                'nextUrl' => $nextUrl,
                'previousUrl' => $previousUrl,
                'search' => $search,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return view('orders.index', [
                'orders' => [],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'hasPreviousPage' => false,
                    'startCursor' => null,
                    'endCursor' => null,
                ],
                'nextUrl' => null,
                'previousUrl' => null,
                'search' => $search,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Search for an order by Shopify order ID/order number.
     *
     * Examples:
     *   1012
     *   #1012
     *   Order #1012
     */
    protected function searchOrder(
        ShopifyToken $shopifyToken,
        string $locationId,
        string $search
    ) {
        /*
         * Keep only the numeric portion.
         *
         * 1012       -> 1012
         * #1012      -> 1012
         * Order #1012 -> 1012
         */
        $orderNumber = preg_replace('/[^0-9]/', '', $search);

        if (blank($orderNumber)) {
            return view('orders.index', [
                'orders' => [],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'hasPreviousPage' => false,
                    'startCursor' => null,
                    'endCursor' => null,
                ],
                'nextUrl' => null,
                'previousUrl' => null,
                'search' => $search,
                'error' => 'Please enter a valid order ID or order number.',
            ]);
        }

        $orderId = 'gid://shopify/Order/' . $orderNumber;

        try {
            $query = <<<'GRAPHQL'
query SearchOrder($id: ID!) {
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
}
GRAPHQL;

            $data = $this->executeForStore(
                $shopifyToken,
                $query,
                [
                    'id' => $orderId,
                ]
            );

            $order = $data['order'] ?? null;

            if (!$order) {
                return view('orders.index', [
                    'orders' => [],
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'hasPreviousPage' => false,
                        'startCursor' => null,
                        'endCursor' => null,
                    ],
                    'nextUrl' => null,
                    'previousUrl' => null,
                    'search' => $search,
                    'error' => "Order #{$orderNumber} was not found.",
                ]);
            }

            /*
             * Only keep fulfillment orders assigned to FSWarehouse.
             */
            $fsFulfillmentOrders = [];

            foreach (
                ($order['fulfillmentOrders']['nodes'] ?? [])
                as $fulfillmentOrder
            ) {
                $assignedLocationId =
                    $fulfillmentOrder['assignedLocation']['location']['id']
                    ?? null;

                if ($assignedLocationId === $locationId) {
                    $fsFulfillmentOrders[] = $fulfillmentOrder;
                }
            }

            if (empty($fsFulfillmentOrders)) {
                return view('orders.index', [
                    'orders' => [],
                    'pageInfo' => [
                        'hasNextPage' => false,
                        'hasPreviousPage' => false,
                        'startCursor' => null,
                        'endCursor' => null,
                    ],
                    'nextUrl' => null,
                    'previousUrl' => null,
                    'search' => $search,
                    'error' => "Order #{$orderNumber} exists, but it is not assigned to FSWarehouse.",
                ]);
            }

            /*
             * Attach the FSWarehouse fulfillment order to the order.
             */
            $order['fsFulfillmentOrders'] = $fsFulfillmentOrders;

            /*
             * Return one order to the Orders page.
             */
            return view('orders.index', [
                'orders' => [$order],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'hasPreviousPage' => false,
                    'startCursor' => null,
                    'endCursor' => null,
                ],
                'nextUrl' => null,
                'previousUrl' => null,
                'search' => $search,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return view('orders.index', [
                'orders' => [],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'hasPreviousPage' => false,
                    'startCursor' => null,
                    'endCursor' => null,
                ],
                'nextUrl' => null,
                'previousUrl' => null,
                'search' => $search,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display a single order.
     *
     * Only FSWarehouse fulfillment orders, line items and fulfillments
     * are displayed.
     */
    public function show(int $orderId)
    {
        /*
         * Always initialize $error so the Blade view can safely use it.
         */
        $error = null;

        $shopifyToken = $this->getShopifyToken();

        $locationId = $shopifyToken->fulfillment_location_id;

        if (blank($locationId)) {
            $error = 'FSWarehouse fulfillment location is not configured.';

            return view('orders.show', [
                'order' => null,
                'error' => $error,
            ]);
        }

        $shopifyOrderId = 'gid://shopify/Order/' . $orderId;

        try {
            $query = <<<'GRAPHQL'
query GetFswWarehouseOrder(
    $first: Int
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

            $data = $this->executeForStore(
                $shopifyToken,
                $query,
                [
                    'first' => 100,
                    'locationIds' => [$locationId],
                ]
            );

            $nodes =
                $data['assignedFulfillmentOrders']['nodes']
                ?? [];

            $matchingFulfillmentOrders = [];

            foreach ($nodes as $fulfillmentOrder) {
                $order = $fulfillmentOrder['order'] ?? null;

                if (!$order) {
                    continue;
                }

                if (($order['id'] ?? null) !== $shopifyOrderId) {
                    continue;
                }

                $matchingFulfillmentOrders[] = $fulfillmentOrder;
            }

            if (empty($matchingFulfillmentOrders)) {
                return view('orders.show', [
                    'order' => null,
                    'error' => "Order #{$orderId} was not found in FSWarehouse.",
                ]);
            }

            /*
             * The order information is the same for the matching
             * FSWarehouse fulfillment orders.
             */
            $order = $matchingFulfillmentOrders[0]['order'];

            /*
             * Attach only FSWarehouse fulfillment orders.
             */
            $order['fsFulfillmentOrders'] =
                $matchingFulfillmentOrders;

            /*
             * Collect only FSWarehouse line items.
             */
            $fsLineItems = [];

            foreach ($matchingFulfillmentOrders as $fulfillmentOrder) {
                foreach (
                    ($fulfillmentOrder['lineItems']['nodes'] ?? [])
                    as $lineItem
                ) {
                    $fsLineItems[] = $lineItem;
                }
            }

            /*
             * Collect only actual FSWarehouse fulfillments.
             */
            $fsFulfillments = [];

            foreach ($matchingFulfillmentOrders as $fulfillmentOrder) {
                foreach (
                    ($fulfillmentOrder['fulfillments']['nodes'] ?? [])
                    as $fulfillment
                ) {
                    $fsFulfillments[] = $fulfillment;
                }
            }

            $order['fsLineItems'] = $fsLineItems;
            $order['fsFulfillments'] = $fsFulfillments;

            return view('orders.show', [
                'order' => $order,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $error = $e->getMessage();

            return view('orders.show', [
                'order' => null,
                'error' => $error,
            ]);
        }
    }

    /**
     * Update tracking information on an existing Shopify fulfillment.
     */
    public function updateTracking(
        Request $request,
        int $orderId,
        int $fulfillmentId
    ) {
        $shopifyToken = $this->getShopifyToken();

        $validated = $request->validate([
            'tracking_number' => [
                'required',
                'string',
                'max:255',
            ],

            'carrier' => [
                'required',
                'string',
                Rule::in(config('shopify.tracking_carriers', [])),
            ],

            'tracking_url' => [
                'nullable',
                'url',
                'max:2048',
            ],
        ]);

        $fulfillmentGid =
            'gid://shopify/Fulfillment/' . $fulfillmentId;

        try {
            /*
             * Security / ownership check:
             *
             * Make sure the fulfillment actually belongs to an
             * FSWarehouse fulfillment order.
             */
            $this->ensureFulfillmentBelongsToFswWarehouse(
                $shopifyToken,
                $fulfillmentGid
            );

            $this->fulfillmentService->updateTracking(
                fulfillmentId: $fulfillmentGid,
                trackingNumber: $validated['tracking_number'],
                carrier: $validated['carrier'],
                trackingUrl: $validated['tracking_url'] ?? null,
                shopifyTokenId: $shopifyToken->id
            );

            return redirect()
                ->route('orders.show', [
                    'orderId' => $orderId,
                ])
                ->with(
                    'success',
                    'Tracking information updated successfully.'
                );
        } catch (\Throwable $e) {
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

    /**
     * Make sure a Shopify fulfillment belongs to FSWarehouse.
     */
    protected function ensureFulfillmentBelongsToFswWarehouse(
        ShopifyToken $shopifyToken,
        string $fulfillmentGid
    ): void {
        $locationId = $shopifyToken->fulfillment_location_id;

        if (blank($locationId)) {
            throw new RuntimeException(
                'FSWarehouse fulfillment location is not configured.'
            );
        }

        $query = <<<'GRAPHQL'
query FindFswWarehouseFulfillment(
    $first: Int
    $locationIds: [ID!]
) {
    assignedFulfillmentOrders(
        first: $first
        locationIds: $locationIds
    ) {
        nodes {
            id

            assignedLocation {
                location {
                    id
                    name
                }
            }

            fulfillments(first: 50) {
                nodes {
                    id
                }
            }
        }
    }
}
GRAPHQL;

        $data = $this->executeForStore(
            $shopifyToken,
            $query,
            [
                'first' => 100,
                'locationIds' => [$locationId],
            ]
        );

        foreach (
            ($data['assignedFulfillmentOrders']['nodes'] ?? [])
            as $fulfillmentOrder
        ) {
            foreach (
                ($fulfillmentOrder['fulfillments']['nodes'] ?? [])
                as $fulfillment
            ) {
                if (($fulfillment['id'] ?? null) === $fulfillmentGid) {
                    return;
                }
            }
        }

        throw new RuntimeException(
            'This fulfillment does not belong to FSWarehouse.'
        );
    }

    /**
     * Get the configured Shopify token.
     */
    protected function getShopifyToken(): ShopifyToken
    {
        $token = ShopifyToken::query()->first();

        if (!$token) {
            throw new RuntimeException(
                'Shopify connection was not found.'
            );
        }

        if (blank($token->shop_domain)) {
            throw new RuntimeException(
                'Shopify store domain is missing.'
            );
        }

        if (blank($token->access_token)) {
            throw new RuntimeException(
                'Shopify access token is missing.'
            );
        }

        return $token;
    }

    /**
     * Execute a Shopify GraphQL query using the existing
     * ShopifyGraphQLService.
     */
    protected function executeForStore(
        ShopifyToken $shopifyToken,
        string $query,
        array $variables = []
    ): array {
        return $this->shopify->executeWithCredentials(
            $shopifyToken->shop_domain,
            $shopifyToken->access_token,
            $query,
            $variables
        );
    }
}