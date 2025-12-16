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
        // MySQL/MariaDB: Modify column type
        DB::statement('ALTER TABLE shipment_destinations MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT "pending"');

        // Note: MySQL/MariaDB doesn't support CHECK constraints like PostgreSQL
        // Validation will be handled at application level
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL/MariaDB: Revert column type
        DB::statement('ALTER TABLE shipment_destinations MODIFY COLUMN status ENUM("pending", "in_progress", "completed", "failed") NOT NULL DEFAULT "pending"');

        // Note: MySQL/MariaDB doesn't support CHECK constraints like PostgreSQL
        // Validation will be handled at application level
    }
};
