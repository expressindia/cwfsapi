<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopify_tokens', function (Blueprint $table) {
            $table->id();

            $table->string('shop_domain')->unique();

            $table->text('access_token');

            $table->text('scope')->nullable();

            /*
             * Shopify online access token user information.
             */
            $table->string('associated_user_id')->nullable();
            $table->string('associated_user_email')->nullable();
            $table->string('associated_user_first_name')->nullable();
            $table->string('associated_user_last_name')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('associated_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_tokens');
    }
};