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

        Schema::create(config('creators-ticketing.table_prefix').'ticket_replies', function (Blueprint $table) use ($userTable, $userKey) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained(config('creators-ticketing.table_prefix').'tickets')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references($userKey)
                ->on($userTable)
                ->cascadeOnDelete();

            $table->text('content');
            $table->boolean('is_internal_note')->default(false);
            $table->boolean('is_seen')->default(false);

            $table->unsignedBigInteger('seen_by')->nullable();
            $table->foreign('seen_by')
                ->references($userKey)
                ->on($userTable)
                ->nullOnDelete();

            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('creators-ticketing.table_prefix').'ticket_replies');
    }
};
