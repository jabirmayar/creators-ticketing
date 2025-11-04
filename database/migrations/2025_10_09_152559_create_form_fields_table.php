<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained(config('creators-ticketing.table_prefix') . 'forms')->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->text('help_text')->nullable();
            $table->text('validation_rules')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'form_fields');
    }
};