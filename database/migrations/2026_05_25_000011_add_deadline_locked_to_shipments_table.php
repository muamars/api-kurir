<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // true = deadline terkunci (user tidak bisa edit)
            // false = admin buka reschedule, user boleh ubah deadline
            $table->boolean('deadline_locked')->default(true)->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('deadline_locked');
        });
    }
};
