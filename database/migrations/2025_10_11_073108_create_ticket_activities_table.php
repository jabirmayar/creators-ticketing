<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
    Schema::create(config('creators-ticketing.table_prefix') . 'ticket_activities', function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId("ticket_id")
        ->constrained(config('creators-ticketing.table_prefix') . 'tickets')
                ->cascadeOnDelete();
            $table
                ->foreignId("user_id")
                ->nullable()
        ->constrained('users')
                ->nullOnDelete();
            $table->string("description");
            $table->text("old_value")->nullable();
            $table->text("new_value")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'ticket_activities');
    }
};
