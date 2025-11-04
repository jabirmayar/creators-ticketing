<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'department_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained(config('creators-ticketing.table_prefix') . 'departments')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained(config('creators-ticketing.table_prefix') . 'forms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'form_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'department_forms');
    }
};