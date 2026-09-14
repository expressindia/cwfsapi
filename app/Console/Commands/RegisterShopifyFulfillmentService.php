<?php

namespace App\Console\Commands;

use App\Services\Shopify\ShopifyGraphQLService;
use Illuminate\Console\Command;
use RuntimeException;

class RegisterShopifyFulfillmentService extends Command
{
    protected $signature = 'shopify:register-fulfillment-service';

    protected $description = 'Register FS-Warehouse as a Shopify fulfillment service';

    public function handle(ShopifyGraphQLService $shopify): int
    {

        $name = config('shopify.fulfillment.service_name');

        $callbackUrl = config( 'shopify.fulfillment.callback_url' );

        if (blank($name)) {

            $this->error( 'SHOPIFY_FULFILLMENT_SERVICE_NAME is missing.' );

            return self::FAILURE;
        }

        if (blank($callbackUrl)) {

            $this->error( 'SHOPIFY_FULFILLMENT_CALLBACK_URL is missing.' );

            return self::FAILURE;
        }

        $mutation = <<<'GRAPHQL'
mutation FulfillmentServiceCreate(
    $name: String!,
    $callbackUrl: URL!,
    $inventoryManagement: Boolean!,
    $trackingSupport: Boolean!,
    $requiresShippingMethod: Boolean!
) {

    fulfillmentServiceCreate(
        name: $name
        callbackUrl: $callbackUrl
        inventoryManagement: $inventoryManagement
        trackingSupport: $trackingSupport
        requiresShippingMethod: $requiresShippingMethod
    ) {

        fulfillmentService {

            id
            serviceName
            callbackUrl

            location {
                id
                name
            }

        }

        userErrors {

            field
            message

        }

    }

}
GRAPHQL;

        $variables = [

            'name' =>  $name,

            'callbackUrl' => $callbackUrl,

            'inventoryManagement' => false,

            'trackingSupport' => true,

            'requiresShippingMethod' => true,

        ];

        try {
            $data = $shopify->execute( $mutation, $variables  );
        } catch ( RuntimeException $e ) {
            $this->error( $e->getMessage() );
            return self::FAILURE;
        }

        $result = $data['fulfillmentServiceCreate'] ?? null;

        if (!$result) {

            $this->error( 'Shopify returned no fulfillmentServiceCreate result.' );

            return self::FAILURE;
        }

        $errors = $result['userErrors'] ?? [];

        if (!empty($errors)) {

            foreach ( $errors as $error ) {

                $this->error( ($error['message'] ?? 'Unknown error') );
            }

            return self::FAILURE;
        }

        $service = $result['fulfillmentService'] ?? null;

        if (!$service) {

            $this->error( 'Shopify did not return the fulfillment service.' );

            return self::FAILURE;
        }

        $this->info( 'Shopify fulfillment service registered successfully.'  );

        $this->line( 'Service Name: ' . ($service['serviceName'] ?? '') );

        $this->line( 'Service ID: ' . ($service['id'] ?? '') );

        $this->line( 'Callback URL: ' .  ($service['callbackUrl'] ?? '') );

        $location = $service['location'] ?? null;

        if ($location) {

            $this->line( 'Location ID: ' . ($location['id'] ?? '') );

            $this->line( 'Location Name: ' . ($location['name'] ?? '') );

            $this->newLine();

            $this->info( 'Add this to your .env:' );

            $this->line( 'SHOPIFY_FULFILLMENT_LOCATION_ID=' . ($location['id'] ?? '') );
        }

        return self::SUCCESS;
    }
}
