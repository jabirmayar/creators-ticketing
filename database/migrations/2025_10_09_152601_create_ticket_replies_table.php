<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained(config('creators-ticketing.table_prefix') . 'tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(config('creators-ticketing.table_prefix') . 'users')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_internal_note')->default(false);
            $table->boolean('is_seen')->default(false);
            $table->foreignId('seen_by')->nullable()->constrained(config('creators-ticketing.table_prefix') . 'users')->nullOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'ticket_replies');
    }
};