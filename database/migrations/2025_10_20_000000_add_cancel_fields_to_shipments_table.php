<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            if (Schema::hasColumn('shipments', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
