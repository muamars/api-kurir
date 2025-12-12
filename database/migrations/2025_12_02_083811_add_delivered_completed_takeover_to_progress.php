<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'delivered', 'completed', 'takeover' status to enum
        DB::statement('ALTER TABLE shipment_progress DROP CONSTRAINT IF EXISTS shipment_progress_status_check');
        DB::statement("ALTER TABLE shipment_progress ADD CONSTRAINT shipment_progress_status_check CHECK (status IN ('picked', 'arrived', 'delivered', 'completed', 'returning', 'finished', 'takeover', 'failed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE shipment_progress DROP CONSTRAINT IF EXISTS shipment_progress_status_check');
        DB::statement("ALTER TABLE shipment_progress ADD CONSTRAINT shipment_progress_status_check CHECK (status IN ('picked', 'arrived', 'delivered', 'returning', 'finished', 'failed'))");
    }
};
