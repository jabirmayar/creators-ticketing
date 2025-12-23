<?php
// database/migrations/2025_01_XX_create_automation_rules_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('creators-ticketing.table_prefix') . 'automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Trigger configuration
            $table->string('trigger_event'); 
            $table->json('trigger_conditions')->nullable();
            
            // Conditions (all must match)
            $table->json('conditions'); 
            
            // Actions to perform
            $table->json('actions'); 
            
            // Execution settings
            $table->integer('execution_order')->default(0); 
            $table->boolean('stop_processing')->default(false);
            
            // Statistics
            $table->integer('times_triggered')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'trigger_event']);
            $table->index('execution_order');
        });
        
        // Automation execution log
        Schema::create(config('creators-ticketing.table_prefix') . 'automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')
                ->constrained(config('creators-ticketing.table_prefix') . 'automation_rules')
                ->cascadeOnDelete();
            $table->foreignId('ticket_id')
                ->constrained(config('creators-ticketing.table_prefix') . 'tickets')
                ->cascadeOnDelete();
            $table->string('trigger_event');
            $table->json('conditions_met');
            $table->json('actions_performed');
            $table->text('error_message')->nullable();
            $table->boolean('success')->default(true);
            $table->timestamps();
            
            $table->index(['ticket_id', 'created_at']);
            $table->index(['automation_rule_id', 'success']);
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'automation_logs');
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'automation_rules');
    }
};