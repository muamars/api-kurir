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
            // MySQL/MariaDB: Modify ENUM to include in_progress
            DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM('pending', 'approved', 'assigned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        } else {
            // PostgreSQL: Use CHECK constraint
            DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_status_check');
            DB::statement("ALTER TABLE shipments ADD CONSTRAINT shipments_status_check CHECK (status IN ('pending', 'approved', 'assigned', 'in_progress', 'completed', 'cancelled'))");
        }
        
        // Update any existing 'created' status to 'pending'
        DB::table('shipments')
            ->where('status', 'created')
            ->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL/MariaDB: Revert ENUM
            DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM('pending', 'approved', 'assigned', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
        } else {
            // PostgreSQL: Revert CHECK constraint
            DB::statement('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_status_check');
            DB::statement("ALTER TABLE shipments ADD CONSTRAINT shipments_status_check CHECK (status IN ('pending', 'approved', 'assigned', 'completed', 'cancelled'))");
        }
    }
};