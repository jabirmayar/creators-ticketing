<?php

use daacreators\CreatorsTicketing\Support\UserColumnNullability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('creators-ticketing.table_prefix').'ticket_replies';
        UserColumnNullability::change($table, true);
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('guest_name')->nullable());
    }

    public function down(): void
    {
        $table = config('creators-ticketing.table_prefix').'ticket_replies';
        UserColumnNullability::change($table, false);
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('guest_name'));
    }
};
