<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_orders', function (Blueprint $table) {

            $table->id();

            $table->string('shopify_order_id')
                ->nullable();

            $table->string('shopify_order_name')
                ->nullable();

            $table->string('shopify_fulfillment_order_id')
                ->unique();

            $table->string('fullscript_order_id')
                ->nullable()
                ->index();

            $table->string('status')
                ->nullable();

            $table->json('fullscript_response')
                ->nullable();

            $table->json('tracking_data')
                ->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_orders');
    }
};