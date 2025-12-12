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
        // Add 'takeover' status to enum
        DB::statement('ALTER TABLE shipment_destinations DROP CONSTRAINT IF EXISTS shipment_destinations_status_check');
        DB::statement("ALTER TABLE shipment_destinations ADD CONSTRAINT shipment_destinations_status_check CHECK (status IN ('pending', 'picked', 'in_progress', 'delivered', 'completed', 'returning', 'finished', 'takeover', 'failed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE shipment_destinations DROP CONSTRAINT IF EXISTS shipment_destinations_status_check');
        DB::statement("ALTER TABLE shipment_destinations ADD CONSTRAINT shipment_destinations_status_check CHECK (status IN ('pending', 'picked', 'in_progress', 'completed', 'returning', 'finished', 'failed'))");
    }
};
