<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
            $table->foreignId('destination_id')->constrained('shipment_destinations')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users');
            $table->enum('status', ['arrived', 'delivered', 'failed'])->default('arrived');
            $table->timestamp('progress_time');
            $table->string('photo_url')->nullable(); // Foto bukti sampai
            $table->string('photo_thumbnail')->nullable(); // Thumbnail foto
            $table->text('note')->nullable();
            $table->string('action_button')->nullable(); // Status tombol aksi
            $table->string('receiver_name')->nullable(); // Nama penerima aktual
            $table->string('received_photo_url')->nullable(); // Foto penerima
            $table->timestamps();

            $table->index(['shipment_id', 'destination_id']);
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_progress');
    }
};
