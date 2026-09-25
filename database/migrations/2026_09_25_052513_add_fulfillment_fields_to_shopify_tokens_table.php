<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_tokens', function (Blueprint $table) {
            $table->string('fulfillment_service_id')
                ->nullable()
                ->after('access_token');

            $table->string('fulfillment_location_id')
                ->nullable()
                ->after('fulfillment_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_service_id',
                'fulfillment_location_id',
            ]);
        });
    }
};