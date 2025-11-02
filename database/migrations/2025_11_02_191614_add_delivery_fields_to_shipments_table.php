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
            $table->datetime('scheduled_delivery_datetime')->nullable()->after('deadline');
            $table->text('courier_notes')->nullable()->after('notes');
            $table->string('attachment_path')->nullable()->after('surat_pengantar_kerja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['scheduled_delivery_datetime', 'courier_notes', 'attachment_path']);
        });
    }
};
