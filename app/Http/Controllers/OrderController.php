<?php

namespace App\Http\Controllers;

use App\Models\ShopifyToken;
use App\Services\Shopify\ShopifyFulfillmentService;
use App\Services\Shopify\ShopifyGraphQLService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        protected ShopifyGraphQLService $shopify,
        protected ShopifyFulfillmentService $fulfillmentService
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

    /**
     * Display Shopify order details.
     */
    public function show(string $orderId): View
    {
        // Convert numeric URL ID back to Shopify GraphQL GID.
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
                    'id' => $shopifyOrderId,
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

        /*
         * Convert numeric fulfillment ID from the URL
         * back to Shopify GraphQL GID.
         */
        $shopifyFulfillmentId =
            'gid://shopify/Fulfillment/' . $fulfillmentId;

        try {

            /*
             * Get the Shopify token used by this application.
             *
             * We use the first active token here because the current
             * standalone Shopify application is connected to one store.
             */
            $shopifyToken = ShopifyToken::query()
                ->where('shop_domain', config('shopify.store_domain'))
                ->whereNotNull('access_token')
                ->first();

            if (!$shopifyToken) {

                throw new \RuntimeException(
                    'Shopify token not found.'
                );
            }

            /*
             * Update the existing Shopify fulfillment.
             *
             * We are NOT creating a new fulfillment.
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
}