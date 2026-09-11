<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_backup', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id');
            $table->string('shipment_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('assigned_driver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->text('courier_notes')->nullable();
            $table->enum('priority', ['regular', 'urgent'])->default('regular');
            $table->dateTime('deadline')->nullable();
            $table->boolean('deadline_locked')->default(true);
            $table->dateTime('scheduled_delivery_datetime')->nullable();
            $table->string('surat_pengantar_kerja')->nullable();
            $table->string('attachment_path')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('tugas_pengiriman_id')->nullable();
            $table->bigInteger('shipping_cost')->nullable();
            $table->string('vehicle_used')->nullable();
            $table->string('online_tracking_url')->nullable();
            $table->string('completion_photo')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->integer('backup_year');
            $table->timestamp('backed_up_at')->useCurrent();

            $table->index('shipment_id');
            $table->index('backup_year');
            $table->index('original_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_backup');
    }
};
