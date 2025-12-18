<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - MySQL compatibility fix
     */
    public function up(): void
    {
        // Check if we're using MySQL
        if (DB::getDriverName() === 'mysql') {
            
            // Fix shipment_destinations status column
            Schema::table('shipment_destinations', function (Blueprint $table) {
                $table->enum('status', [
                    'pending', 'picked', 'in_progress', 'delivered', 
                    'completed', 'returning', 'finished', 'takeover', 'failed'
                ])->default('pending')->change();
            });

            // Fix shipment_progress status column  
            Schema::table('shipment_progress', function (Blueprint $table) {
                $table->enum('status', [
                    'picked', 'arrived', 'delivered', 'returning', 'finished', 'failed'
                ])->default('picked')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback not needed for compatibility fix
    }
};