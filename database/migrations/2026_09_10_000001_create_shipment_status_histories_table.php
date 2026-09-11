<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->string('action'); // create, approve, assign, reassign, takeover, start_delivery, progress_update, complete, cancel, reschedule
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('previous_driver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Additional context: takeover_count, approved_by, etc.
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['shipment_id', 'changed_at']);
            $table->index(['driver_id', 'changed_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_histories');
    }
};
