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
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('vehicle_type_id')->nullable()->after('category_id')->constrained('vehicle_types')->restrictOnDelete();
            $table->index('vehicle_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['vehicle_type_id']);
            $table->dropIndex(['vehicle_type_id']);
            $table->dropColumn('vehicle_type_id');
        });
    }
};
