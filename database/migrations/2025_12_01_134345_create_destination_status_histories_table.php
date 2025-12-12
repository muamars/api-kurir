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
        Schema::create('destination_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained('shipment_destinations')->onDelete('cascade');
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->string('old_status');
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('note')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['destination_id', 'changed_at']);
            $table->index('shipment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destination_status_histories');
    }
};
