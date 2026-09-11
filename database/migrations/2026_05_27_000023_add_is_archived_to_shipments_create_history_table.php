<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flag pada tabel aktif agar FK tetap utuh saat arsip
        Schema::table('shipments', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('cancel_reason')->index();
            $table->timestamp('archived_at')->nullable()->after('is_archived');
        });

        // Tabel arsip: snapshot mandiri, tidak pakai FK ke tabel lain
        Schema::create('shipment_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_row_id'); // id asli dari tabel shipments

            // --- kolom utama shipments ---
            $table->string('shipment_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('assigned_driver_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('tugas_pengiriman_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->text('courier_notes')->nullable();
            $table->string('priority')->default('regular');
            $table->dateTime('deadline')->nullable();
            $table->boolean('deadline_locked')->default(true);
            $table->dateTime('scheduled_delivery_datetime')->nullable();
            $table->string('surat_pengantar_kerja')->nullable();
            $table->string('attachment_path')->nullable();
            $table->bigInteger('shipping_cost')->nullable();
            $table->string('vehicle_used')->nullable();
            $table->string('online_tracking_url')->nullable();
            $table->string('completion_photo')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps(); // created_at/updated_at asli dari shipments

            // --- snapshot relasi (JSON) ---
            $table->json('destinations_snapshot')->nullable();
            $table->json('items_snapshot')->nullable();

            // --- metadata arsip ---
            $table->timestamp('archived_at')->useCurrent();
            $table->string('archive_reason')->default('completed'); // completed | cancelled | manual

            $table->index('shipment_id');
            $table->index('status');
            $table->index('assigned_driver_id');
            $table->index('archived_at');
            $table->index('shipment_row_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_history');

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['is_archived']);
            $table->dropColumn(['is_archived', 'archived_at']);
        });
    }
};
