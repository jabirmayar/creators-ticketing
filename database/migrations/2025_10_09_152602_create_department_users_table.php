<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $userInstance = new $userModel;
        $userTable = $userInstance->getTable();
        $userKey = $userInstance->getKeyName();

        Schema::create(config('creators-ticketing.table_prefix') . 'department_users', function (Blueprint $table) use ($userTable, $userKey) {
            $table->foreignId('department_id')
                ->constrained(config('creators-ticketing.table_prefix') . 'departments')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id');

            $table->foreign('user_id')
                ->references($userKey)
                ->on($userTable)
                ->cascadeOnDelete();

            $table->primary(['department_id', 'user_id']);

            $table->string('role')->default('agent');
            $table->boolean('can_create_tickets')->default(false);
            $table->boolean('can_view_all_tickets')->default(false);
            $table->boolean('can_assign_tickets')->default(false);
            $table->boolean('can_change_departments')->default(false);
            $table->boolean('can_change_status')->default(false);
            $table->boolean('can_change_priority')->default(false);
            $table->boolean('can_delete_tickets')->default(false);
            $table->boolean('can_reply_to_tickets')->default(false);
            $table->boolean('can_add_internal_notes')->default(false);
            $table->boolean('can_view_internal_notes')->default(false);
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'department_users');
    }
};