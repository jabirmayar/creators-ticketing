<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('creators-ticketing.table_prefix') . 'department_users', function (Blueprint $table) {
            $table->boolean('can_manage_automations')->default(false)->after('can_view_internal_notes');
            $table->boolean('can_view_automation_logs')->default(false)->after('can_manage_automations');
        });
    }

    public function down(): void
    {
        Schema::table(config('creators-ticketing.table_prefix') . 'department_users', function (Blueprint $table) {
            $table->dropColumn([
                'can_manage_automations',
                'can_view_automation_logs',
            ]);
        });
    }
};
