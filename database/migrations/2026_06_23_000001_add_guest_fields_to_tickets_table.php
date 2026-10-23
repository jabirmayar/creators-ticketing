<?php

use daacreators\CreatorsTicketing\Support\UserColumnNullability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('creators-ticketing.table_prefix').'tickets';
        UserColumnNullability::change($table, true);
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->string('guest_name')->nullable();
            $blueprint->string('guest_email')->nullable();
            $blueprint->string('guest_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        $table = config('creators-ticketing.table_prefix').'tickets';
        UserColumnNullability::change($table, false);
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropUnique(['guest_token']);
            $blueprint->dropColumn(['guest_name', 'guest_email', 'guest_token']);
        });
    }
};
