<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $prefix = config('creators-ticketing.table_prefix', 'ct_');

        Schema::table($prefix.'tickets', function (Blueprint $table) use ($prefix) {
            $table->foreignId('form_id')
                ->nullable()
                ->after('department_id')
                ->constrained($prefix.'forms')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        $prefix = config('creators-ticketing.table_prefix', 'ct_');

        Schema::table($prefix.'tickets', function (Blueprint $table) {
            $table->dropForeign(['form_id']);
            $table->dropColumn('form_id');
        });
    }
};
