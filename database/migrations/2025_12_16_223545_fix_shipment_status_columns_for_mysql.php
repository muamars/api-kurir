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
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB syntax
            DB::statement('ALTER TABLE shipment_destinations MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT "pending"');
            DB::statement('ALTER TABLE shipment_progress MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT "picked"');
        } elseif ($driver === 'pgsql') {
            // PostgreSQL syntax
            DB::statement('ALTER TABLE shipment_destinations ALTER COLUMN status TYPE VARCHAR(50)');
            DB::statement('ALTER TABLE shipment_destinations ALTER COLUMN status SET DEFAULT \'pending\'');
            DB::statement('ALTER TABLE shipment_progress ALTER COLUMN status TYPE VARCHAR(50)');
            DB::statement('ALTER TABLE shipment_progress ALTER COLUMN status SET DEFAULT \'picked\'');
        }

        // Note: CHECK constraints are not supported in MySQL/MariaDB
        // Status validation will be handled at application level
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Revert to enum types
            DB::statement('ALTER TABLE shipment_destinations MODIFY COLUMN status ENUM("pending", "picked", "in_progress", "arrived", "delivered", "completed", "returning", "finished", "takeover", "failed") NOT NULL DEFAULT "pending"');
            DB::statement('ALTER TABLE shipment_progress MODIFY COLUMN status ENUM("picked", "in_progress", "arrived", "delivered", "completed", "returning", "finished", "takeover", "failed") NOT NULL DEFAULT "picked"');
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Revert to text with check constraints
            DB::statement('ALTER TABLE shipment_destinations ALTER COLUMN status TYPE TEXT');
            DB::statement('ALTER TABLE shipment_destinations ALTER COLUMN status SET DEFAULT \'pending\'');
            DB::statement('ALTER TABLE shipment_progress ALTER COLUMN status TYPE TEXT');
            DB::statement('ALTER TABLE shipment_progress ALTER COLUMN status SET DEFAULT \'picked\'');
        }
    }
};
