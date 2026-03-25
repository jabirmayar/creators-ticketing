<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use daacreators\CreatorsTicketing\Support\UserForeignKey;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_uid')->unique();

            UserForeignKey::add($table, 'user_id', nullable: false, onDelete: 'cascade');

            $table->foreignId('department_id')
                ->constrained(config('creators-ticketing.table_prefix') . 'departments')
                ->cascadeOnDelete();

            UserForeignKey::add($table, 'assignee_id', nullable: true, onDelete: 'null');

            $table->foreignId('ticket_status_id')
                ->constrained(config('creators-ticketing.table_prefix') . 'ticket_statuses')
                ->cascadeOnDelete();

            $table->string('priority')->default('low');
            $table->json('custom_fields')->nullable();
            $table->boolean('is_seen')->default(false);

            UserForeignKey::add($table, 'seen_by', nullable: true, onDelete: 'null');

            $table->timestamp('seen_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'tickets');
    }
};
