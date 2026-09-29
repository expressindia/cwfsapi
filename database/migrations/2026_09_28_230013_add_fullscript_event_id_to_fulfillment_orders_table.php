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
        Schema::table('fulfillment_orders', function (Blueprint $table) {
            $table->string('fullscript_event_id')
                ->nullable()
                ->after('fullscript_order_id');

            $table->index('fullscript_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fulfillment_orders', function (Blueprint $table) {
            $table->dropIndex(['fullscript_event_id']);
            $table->dropColumn('fullscript_event_id');
        });
    }
};
