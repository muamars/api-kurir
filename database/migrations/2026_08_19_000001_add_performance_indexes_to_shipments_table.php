<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->index('created_by', 'idx_shipments_created_by');
            $table->index('created_at', 'idx_shipments_created_at');
            $table->index('completed_at', 'idx_shipments_completed_at');
            $table->index(['assigned_driver_id', 'status'], 'idx_shipments_driver_status');
            $table->index(['created_by', 'status'], 'idx_shipments_creator_status');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('idx_shipments_created_by');
            $table->dropIndex('idx_shipments_created_at');
            $table->dropIndex('idx_shipments_completed_at');
            $table->dropIndex('idx_shipments_driver_status');
            $table->dropIndex('idx_shipments_creator_status');
        });
    }
};
