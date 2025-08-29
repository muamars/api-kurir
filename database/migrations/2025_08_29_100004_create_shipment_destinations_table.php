<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
            $table->string('receiver_name');
            $table->text('delivery_address');
            $table->text('shipment_note')->nullable();
            $table->integer('sequence_order')->default(1); // Urutan pengiriman
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            $table->index(['shipment_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_destinations');
    }
};
