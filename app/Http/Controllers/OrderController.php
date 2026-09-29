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
    ) {
    }

    /**
     * Display orders assigned to this app's fulfillment service.
     *
     * This uses Shopify's assignedFulfillmentOrders connection,
     * so we only receive fulfillment orders assigned to locations
     * managed by this app.
     */
    public function index(): View
    {
        try {
            /*
             * Get the Shopify token for this store.
             */
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

            /*
             * Make sure our registered fulfillment location
             * exists.
             */
            $locationId = $shopifyToken->fulfillment_location_id;

            if (blank($locationId)) {
                throw new RuntimeException(
                    'Shopify fulfillment location ID is not configured.'
                );
            }

            /*
             * Get fulfillment orders assigned to this app.
             *
             * assignedFulfillmentOrders is specifically designed
             * for fulfillment-service apps.
             *
             * The locationIds filter makes sure we only retrieve
             * fulfillment orders assigned to our registered
             * FSWarehouse location.
             */
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
                $data['assignedFulfillmentOrders']['nodes']
                ?? [];

            $pageInfo =
                $data['assignedFulfillmentOrders']['pageInfo']
                ?? [];

            /*
             * An order can have multiple fulfillment orders.
             *
             * Therefore, use the Shopify Order GID as the array key
             * to prevent duplicate orders in the Orders page.
             */
            $orders = [];

            foreach ($assignedFulfillmentOrders as $fulfillmentOrder) {
                $order = $fulfillmentOrder['order'] ?? null;

                if (! $order || empty($order['id'])) {
                    continue;
                }

                $orderId = $order['id'];

                /*
                 * Store the first occurrence of this order.
                 */
                if (! isset($orders[$orderId])) {
                    $orders[$orderId] = $order;
                }
            }

            /*
             * Convert associative array to normal indexed array.
             */
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

    /**
     * Display a single Shopify order.
     */
    public function show(string $orderId): View
    {
        $shopifyOrderId =
            'gid://shopify/Order/' . $orderId;

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
        GRAPHQL;

        try {
            $data = $this->shopify->execute(
                $query,
                [
                    'id' => $shopifyOrderId,
                ]
            );

            $order = $data['order'] ?? null;

            if (! $order) {
                abort(404, 'Shopify order not found.');
            }

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

    /**
     * Update tracking information for a Shopify fulfillment.
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
            /*
             * Get the Shopify token for this store.
             */
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

            /*
             * Update existing Shopify fulfillment tracking.
             *
             * We only send:
             * - tracking number
             * - shipping carrier
             *
             * Tracking URL is intentionally null.
             */
            $this->fulfillmentService->updateTracking(
                $shopifyFulfillmentId,
                $validated['tracking_number'],
                $validated['company'],
                null,
                $shopifyToken->id
            );

            return redirect()
                ->route(
                    'orders.show',
                    [
                        'orderId' => $orderId,
                    ]
                )
                ->with(
                    'success',
                    'Tracking information updated successfully.'
                );

        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route(
                    'orders.show',
                    [
                        'orderId' => $orderId,
                    ]
                )
                ->withInput()
                ->with(
                    'error',
                    'Unable to update tracking information: ' .
                    $e->getMessage()
                );
        }
    }
}