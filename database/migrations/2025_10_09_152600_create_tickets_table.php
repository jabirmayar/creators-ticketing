<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $userInstance = new $userModel;
        $userTable = $userInstance->getTable();
        $userKey = $userInstance->getKeyName();

        Schema::create(config('creators-ticketing.table_prefix').'tickets', function (Blueprint $table) use ($userTable, $userKey) {
            $table->id();
            $table->string('ticket_uid')->unique();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references($userKey)
                ->on($userTable)
                ->cascadeOnDelete();

            $table->foreignId('department_id')
                ->constrained(config('creators-ticketing.table_prefix').'departments')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->foreign('assignee_id')
                ->references($userKey)
                ->on($userTable)
                ->nullOnDelete();

            $table->foreignId('ticket_status_id')
                ->constrained(config('creators-ticketing.table_prefix').'ticket_statuses')
                ->cascadeOnDelete();

            $table->string('priority')->default('low');
            $table->json('custom_fields')->nullable();
            $table->boolean('is_seen')->default(false);

            $table->unsignedBigInteger('seen_by')->nullable();
            $table->foreign('seen_by')
                ->references($userKey)
                ->on($userTable)
                ->nullOnDelete();

            $table->timestamp('seen_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('creators-ticketing.table_prefix').'tickets');
    }
};
