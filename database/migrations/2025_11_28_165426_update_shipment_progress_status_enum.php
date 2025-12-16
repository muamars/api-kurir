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
        // MySQL/MariaDB: Modify column type and default
        DB::statement('ALTER TABLE shipment_progress MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT "picked"');

        // Note: MySQL/MariaDB doesn't support CHECK constraints like PostgreSQL
        // Validation will be handled at application level
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL/MariaDB: Revert column type and default
        DB::statement('ALTER TABLE shipment_progress MODIFY COLUMN status ENUM("arrived", "delivered", "failed") NOT NULL DEFAULT "arrived"');

        // Note: MySQL/MariaDB doesn't support CHECK constraints like PostgreSQL
        // Validation will be handled at application level
    }
};
