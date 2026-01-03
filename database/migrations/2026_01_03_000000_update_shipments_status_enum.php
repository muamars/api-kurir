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
        // PostgreSQL: Update shipments status to include all valid statuses
        DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_status_check');
        DB::statement('ALTER TABLE shipments ALTER COLUMN status TYPE VARCHAR(50)');
        DB::statement("ALTER TABLE shipments ADD CONSTRAINT shipments_status_check CHECK (status IN ('pending', 'approved', 'assigned', 'in_progress', 'completed', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_status_check');
        DB::statement("ALTER TABLE shipments ADD CONSTRAINT shipments_status_check CHECK (status IN ('pending', 'approved', 'assigned', 'completed', 'cancelled'))");
    }
};