<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use daacreators\CreatorsTicketing\Support\UserForeignKey;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Spam filter table
        Schema::create(config('creators-ticketing.table_prefix') . 'spam_filters', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['keyword', 'email', 'ip', 'pattern']);
            $table->enum('action', ['block', 'allow']);
            $table->json('values');
            $table->boolean('is_active')->default(true);
            $table->boolean('case_sensitive')->default(false);
            $table->integer('priority')->default(0);
            $table->text('reason')->nullable();
            $table->integer('hits')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index('priority');
        });

        // Spam log table
        Schema::create(config('creators-ticketing.table_prefix') . 'spam_logs', function (Blueprint $table) {
            $table->id();
            UserForeignKey::add($table, 'user_id', nullable: true, onDelete: 'null');
            $table->foreignId('spam_filter_id')->nullable()->constrained(config('creators-ticketing.table_prefix') . 'spam_filters')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_address')->nullable();
            $table->enum('filter_type', ['keyword', 'email', 'ip', 'pattern', 'rate_limit']);
            $table->enum('action_taken', ['blocked']);
            $table->text('matched_value')->nullable();
            $table->json('ticket_data')->nullable();
            $table->timestamps();
            
            $table->index('created_at');
            $table->index(['email', 'created_at']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'spam_logs');
        Schema::dropIfExists(config('creators-ticketing.table_prefix') . 'spam_filters');
    }

};
