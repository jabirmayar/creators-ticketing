<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_uid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained(config('creators-ticketing.table_prefix') . 'departments')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ticket_status_id')->constrained(config('creators-ticketing.table_prefix') . 'ticket_statuses')->cascadeOnDelete();
            $table->string('priority')->default('low');
            $table->json('custom_fields')->nullable();
            $table->boolean('is_seen')->default(false);
            $table->foreignId('seen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'tickets');
    }
};