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
            // MySQL/MariaDB: Add arrived to ENUM
            DB::statement("ALTER TABLE shipment_destinations MODIFY COLUMN status ENUM('pending', 'picked', 'in_progress', 'arrived', 'delivered', 'completed', 'returning', 'finished', 'takeover', 'failed') NOT NULL DEFAULT 'pending'");
        } else {
            // PostgreSQL: Update CHECK constraint
            DB::statement('ALTER TABLE shipment_destinations DROP CONSTRAINT IF EXISTS shipment_destinations_status_check');
            DB::statement("ALTER TABLE shipment_destinations ADD CONSTRAINT shipment_destinations_status_check CHECK (status IN ('pending', 'picked', 'in_progress', 'arrived', 'delivered', 'completed', 'returning', 'finished', 'takeover', 'failed'))");
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
            // MySQL/MariaDB: Remove arrived from ENUM
            DB::statement("ALTER TABLE shipment_destinations MODIFY COLUMN status ENUM('pending', 'picked', 'in_progress', 'delivered', 'completed', 'returning', 'finished', 'takeover', 'failed') NOT NULL DEFAULT 'pending'");
        } else {
            // PostgreSQL: Revert CHECK constraint
            DB::statement('ALTER TABLE shipment_destinations DROP CONSTRAINT IF EXISTS shipment_destinations_status_check');
            DB::statement("ALTER TABLE shipment_destinations ADD CONSTRAINT shipment_destinations_status_check CHECK (status IN ('pending', 'picked', 'in_progress', 'delivered', 'completed', 'returning', 'finished', 'takeover', 'failed'))");
        }
    }
};