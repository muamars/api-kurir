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
            $table->foreignId('category_id')->nullable()->constrained('shipment_categories')->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('tugas_pengiriman_id')->nullable()->constrained('tugas_pengiriman')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('courier_notes')->nullable();
            $table->enum('priority', ['regular', 'urgent'])->default('regular');
            $table->dateTime('deadline')->nullable();
            $table->boolean('deadline_locked')->default(true);
            $table->dateTime('scheduled_delivery_datetime')->nullable();
            $table->string('surat_pengantar_kerja')->nullable(); // File path
            $table->string('attachment_path')->nullable();
            $table->bigInteger('shipping_cost')->nullable();
            $table->string('vehicle_used')->nullable();
            $table->string('online_tracking_url')->nullable();
            $table->string('completion_photo')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('assigned_driver_id');
            $table->index('category_id');
            $table->index('vehicle_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
