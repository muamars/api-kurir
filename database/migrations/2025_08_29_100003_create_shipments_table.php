<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_id')->unique(); // SPJ Number
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('assigned_driver_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('pending'); // pending, approved, assigned, in_progress, completed, cancelled
            $table->text('notes')->nullable();
            $table->enum('priority', ['regular', 'urgent'])->default('regular');
            $table->dateTime('deadline')->nullable();
            $table->string('surat_pengantar_kerja')->nullable(); // File path
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('assigned_driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
