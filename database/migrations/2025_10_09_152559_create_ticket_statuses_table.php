<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
    Schema::create(config('creators-ticketing.table_prefix') . 'ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color')->default('#84cc16');
            $table->boolean('is_default_for_new')->default(false);
            $table->boolean('is_closing_status')->default(false);
            $table->integer('order_column')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'ticket_statuses');
    }
};