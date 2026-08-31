<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_syncs', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Fullscript
            |--------------------------------------------------------------------------
            */

            $table->string(
                'fullscript_product_id'
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
                'shopify_handle'
            )->nullable();

            $table->string(
                'shopify_product_status'
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

            $table->index(
                'status'
            );

            $table->index(
                'shopify_product_id'
            );

            $table->index(
                'shopify_product_status'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_syncs'
        );
    }
};