<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->text('item_name')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->string('item_name', 255)->change();
        });
    }
};

