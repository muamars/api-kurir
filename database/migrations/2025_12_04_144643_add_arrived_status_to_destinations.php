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
        // MySQL/MariaDB: No CHECK constraints needed
        // Status validation will be handled at application level
        // The column is already VARCHAR(50) from previous migration
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL/MariaDB: No CHECK constraints to remove
        // Status validation will be handled at application level
    }
};
