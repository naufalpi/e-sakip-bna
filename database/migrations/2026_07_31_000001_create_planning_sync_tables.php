<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_module', 50)->index();
            $table->string('target_module', 50)->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('status', 30)->default('previewed')->index();
            $table->jsonb('filters')->nullable();
            $table->jsonb('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['source_module', 'target_module', 'tahun'], 'planning_sync_direction_year_index');
        });

        Schema::create('planning_sync_batch_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_sync_batch_id')->constrained('planning_sync_batches')->cascadeOnDelete();
            $table->string('source_table', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('target_table', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('match_key')->index();
            $table->string('action', 30)->index();
            $table->jsonb('diff_values')->nullable();
            $table->boolean('selected')->default(false)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['planning_sync_batch_id', 'action'], 'planning_sync_row_batch_action_index');
            $table->index(['source_table', 'source_id'], 'planning_sync_row_source_index');
            $table->index(['target_table', 'target_id'], 'planning_sync_row_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_sync_batch_rows');
        Schema::dropIfExists('planning_sync_batches');
    }
};
