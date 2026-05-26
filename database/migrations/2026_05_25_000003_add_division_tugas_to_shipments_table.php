<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('category_id')->constrained('divisions')->nullOnDelete();
            $table->foreignId('tugas_pengiriman_id')->nullable()->after('division_id')->constrained('tugas_pengiriman')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Division::class ?? 'divisions');
            $table->dropForeignIdFor(\App\Models\TugasPengiriman::class ?? 'tugas_pengiriman');
            $table->dropColumn(['division_id', 'tugas_pengiriman_id']);
        });
    }
};
