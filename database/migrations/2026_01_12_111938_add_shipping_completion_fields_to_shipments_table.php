<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->bigInteger('shipping_cost')->nullable()->comment('Biaya pengiriman dalam rupiah (integer)');
            $table->string('vehicle_used')->nullable()->comment('Jenis kendaraan yang digunakan (input bebas)');
            $table->string('completion_photo')->nullable()->comment('Path foto bukti pengiriman');
            $table->timestamp('completed_at')->nullable()->comment('Waktu shipment diselesaikan');
            $table->unsignedBigInteger('completed_by')->nullable()->comment('Admin yang menyelesaikan shipment');
            
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['completed_by']);
            $table->dropColumn([
                'shipping_cost',
                'vehicle_used', 
                'completion_photo',
                'completed_at',
                'completed_by'
            ]);
        });
    }
};
