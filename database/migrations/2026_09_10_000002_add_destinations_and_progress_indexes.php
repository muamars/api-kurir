<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_destinations', function (Blueprint $table) {
            $table->index(['shipment_id', 'status'], 'idx_destinations_shipment_status');
            $table->index('status', 'idx_destinations_status');
        });

        Schema::table('shipment_progress', function (Blueprint $table) {
            $table->index(['destination_id', 'progress_time'], 'idx_progress_destination_time');
            $table->index(['driver_id', 'progress_time'], 'idx_progress_driver_time');
            $table->index('status', 'idx_progress_status');
        });

        Schema::table('destination_status_histories', function (Blueprint $table) {
            $table->index(['destination_id', 'new_status'], 'idx_dest_histories_dest_status');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_destinations', function (Blueprint $table) {
            $table->dropIndex('idx_destinations_shipment_status');
            $table->dropIndex('idx_destinations_status');
        });

        Schema::table('shipment_progress', function (Blueprint $table) {
            $table->dropIndex('idx_progress_destination_time');
            $table->dropIndex('idx_progress_driver_time');
            $table->dropIndex('idx_progress_status');
        });

        Schema::table('destination_status_histories', function (Blueprint $table) {
            $table->dropIndex('idx_dest_histories_dest_status');
        });
    }
};
