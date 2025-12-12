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
        // PostgreSQL: Drop old enum constraint and create new one
        DB::statement('ALTER TABLE shipment_progress DROP CONSTRAINT IF EXISTS shipment_progress_status_check');
        DB::statement('ALTER TABLE shipment_progress ALTER COLUMN status TYPE VARCHAR(50)');
        DB::statement("ALTER TABLE shipment_progress ADD CONSTRAINT shipment_progress_status_check CHECK (status IN ('picked', 'arrived', 'delivered', 'returning', 'finished', 'failed'))");

        // Update default value
        DB::statement("ALTER TABLE shipment_progress ALTER COLUMN status SET DEFAULT 'picked'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE shipment_progress DROP CONSTRAINT IF EXISTS shipment_progress_status_check');
        DB::statement("ALTER TABLE shipment_progress ADD CONSTRAINT shipment_progress_status_check CHECK (status IN ('arrived', 'delivered', 'failed'))");
        DB::statement("ALTER TABLE shipment_progress ALTER COLUMN status SET DEFAULT 'arrived'");
    }
};
