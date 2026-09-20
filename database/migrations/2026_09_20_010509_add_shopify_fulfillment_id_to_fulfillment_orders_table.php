<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_orders', function (Blueprint $table) {
            $table->string('shopify_fulfillment_id')
                ->nullable()
                ->after('shopify_fulfillment_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_orders', function (Blueprint $table) {
            $table->dropColumn('shopify_fulfillment_id');
        });
    }
};