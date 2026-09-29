<?php

namespace App\Http\Controllers;

use App\Services\Shopify\ShopifyGraphQLService;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        protected ShopifyGraphQLService $shopify
    ) {
    }

    /**
     * Display Shopify orders.
     */
    public function index(): View
    {
        $query = <<<'GRAPHQL'
        query GetOrders($first: Int!, $after: String) {
            orders(
                first: $first
                after: $after
                sortKey: CREATED_AT
                reverse: true
            ) {
                nodes {
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
                        trackingInfo {
                            company
                            number
                            url
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

        try {

            $data = $this->shopify->execute(
                $query,
                [
                    'first' => 50,
                    'after' => null,
                ]
            );

            return view('orders.index', [
                'orders' => $data['orders']['nodes'] ?? [],
                'pageInfo' => $data['orders']['pageInfo'] ?? [],
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

    public function show(string $orderId): View
    {
        $shopifyOrderId = 'gid://shopify/Order/' . $orderId;
            
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
                    'id' => $orderId,
                ]
            );

            $order = $data['order'] ?? null;

            if (!$order) {
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
}