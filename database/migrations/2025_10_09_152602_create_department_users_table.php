<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'department_users', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained(config('creators-ticketing.table_prefix') . 'departments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['department_id', 'user_id']);
            $table->string('role')->default('agent');
            $table->string('can_create_tickets')->default('false')->after('role');
            $table->boolean('can_view_all_tickets')->default(false)->after('can_create_tickets');
            $table->boolean('can_assign_tickets')->default(false)->after('can_view_all_tickets');
            $table->boolean('can_change_departments')->default(false)->after('can_assign_tickets');
            $table->boolean('can_change_status')->default(false)->after('can_change_departments');
            $table->boolean('can_change_priority')->default(false)->after('can_change_status');
            $table->boolean('can_delete_tickets')->default(false)->after('can_change_priority');
            $table->boolean('can_reply_to_tickets')->default(false)->after('can_delete_tickets');
            $table->boolean('can_add_internal_notes')->default(false)->after('can_reply_to_tickets');
            $table->boolean('can_view_internal_notes')->default(false)->after('can_add_internal_notes');

        });
    }
    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'department_users');
    }
};