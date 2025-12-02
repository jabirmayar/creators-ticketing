<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('creators-ticketing.table_prefix').'ticket_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained(config('creators-ticketing.table_prefix').'tickets')
                ->cascadeOnDelete();

            $usersTable = config('creators-ticketing.user_table', 'users');

            $userKey = DB::getSchemaBuilder()
                ->getColumnListing($usersTable);

            $userPrimaryKey = collect($userKey)->first(fn ($c) => in_array(strtolower($c), ['id', 'sqlid', 'user_id'])) ?? 'id';

            $table->unsignedBigInteger('user_id')->nullable();

            if (Schema::hasColumn($usersTable, $userPrimaryKey)) {
                $table->foreign('user_id')
                    ->references($userPrimaryKey)
                    ->on($usersTable)
                    ->nullOnDelete();
            }

            $table->string('description');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('creators-ticketing.table_prefix').'ticket_activities');
    }
};
