<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if we're using MySQL/MariaDB
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL/MariaDB: Add in_progress to ENUM
            DB::statement("ALTER TABLE shipment_progress MODIFY COLUMN status ENUM('picked', 'in_progress', 'arrived', 'delivered', 'returning', 'finished', 'takeover', 'failed') NOT NULL DEFAULT 'picked'");
        } else {
            // PostgreSQL: Update CHECK constraint
            DB::statement('ALTER TABLE shipment_progress DROP CONSTRAINT IF EXISTS shipment_progress_status_check');
            DB::statement("ALTER TABLE shipment_progress ADD CONSTRAINT shipment_progress_status_check CHECK (status IN ('picked', 'in_progress', 'arrived', 'delivered', 'returning', 'finished', 'takeover', 'failed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL/MariaDB: Remove in_progress from ENUM
            DB::statement("ALTER TABLE shipment_progress MODIFY COLUMN status ENUM('picked', 'arrived', 'delivered', 'returning', 'finished', 'failed') NOT NULL DEFAULT 'picked'");
        } else {
            // PostgreSQL: Revert CHECK constraint
            DB::statement('ALTER TABLE shipment_progress DROP CONSTRAINT IF EXISTS shipment_progress_status_check');
            DB::statement("ALTER TABLE shipment_progress ADD CONSTRAINT shipment_progress_status_check CHECK (status IN ('picked', 'arrived', 'delivered', 'returning', 'finished', 'failed'))");
        }
    }
};