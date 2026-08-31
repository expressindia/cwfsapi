<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Fullscript
            |--------------------------------------------------------------------------
            */

            $table->string(
                'fullscript_product_id'
            );

            $table->string(
                'fullscript_variant_id'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | SKU
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | SKU is globally unique in our mapping layer.
            |
            */

            $table->string(
                'sku'
            )->unique();

            /*
            |--------------------------------------------------------------------------
            | Shopify
            |--------------------------------------------------------------------------
            */

            $table->string(
                'shopify_product_id'
            )->nullable();

            $table->string(
                'shopify_product_status'
            )->nullable();

            $table->string(
                'shopify_variant_id'
            )->nullable();

            $table->string(
                'shopify_inventory_item_id'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger(
                'fullscript_quantity'
            )->nullable();

            $table->unsignedInteger(
                'shopify_quantity'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sync
            |--------------------------------------------------------------------------
            */

            $table->string(
                'status'
            )->default('pending');

            $table->unsignedInteger(
                'sync_attempts'
            )->default(0);

            $table->text(
                'last_error'
            )->nullable();

            $table->timestamp(
                'last_synced_at'
            )->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                'fullscript_product_id'
            );

            $table->index(
                'fullscript_variant_id'
            );

            $table->index(
                'shopify_product_id'
            );

            $table->index(
                'shopify_variant_id'
            );

            $table->index(
                'shopify_inventory_item_id'
            );

            $table->index(
                'shopify_product_status'
            );

            $table->index(
                'status'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_variants'
        );
    }
};