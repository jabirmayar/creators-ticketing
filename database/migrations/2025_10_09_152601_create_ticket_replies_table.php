<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use daacreators\CreatorsTicketing\Support\UserForeignKey;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'ticket_replies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained(config('creators-ticketing.table_prefix') . 'tickets')
                ->cascadeOnDelete();

            UserForeignKey::add($table, 'user_id', nullable: false, onDelete: 'cascade');

            $table->text('content');
            $table->boolean('is_internal_note')->default(false);
            $table->boolean('is_seen')->default(false);

            UserForeignKey::add($table, 'seen_by', nullable: true, onDelete: 'null');

            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'ticket_replies');
    }
};
