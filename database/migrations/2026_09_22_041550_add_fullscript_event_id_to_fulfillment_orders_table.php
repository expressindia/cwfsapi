<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_orders', function (Blueprint $table) {
            $table->string('fullscript_event_id')
                ->nullable()
                ->unique()
                ->after('fullscript_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_orders', function (Blueprint $table) {
            $table->dropUnique([
                'fullscript_event_id',
            ]);

            $table->dropColumn('fullscript_event_id');
        });
    }
};