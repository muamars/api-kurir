<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Berapa kali pengiriman ini sudah di-takeover admin (untuk membatasi loop assign/takeover)
            $table->unsignedSmallInteger('takeover_count')->default(0)->after('status');
            // Ditandai true bila batas takeover tercapai -> butuh peninjauan supervisor, jangan didaur ulang otomatis
            $table->boolean('needs_review')->default(false)->after('takeover_count');
            // Waktu takeover terakhir (audit ringan)
            $table->timestamp('last_takeover_at')->nullable()->after('needs_review');

            $table->index('needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['needs_review']);
            $table->dropColumn(['takeover_count', 'needs_review', 'last_takeover_at']);
        });
    }
};
