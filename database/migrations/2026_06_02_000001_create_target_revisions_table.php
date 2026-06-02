<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->index();
            $table->string('target_table', 80);
            $table->unsignedBigInteger('target_id');
            $table->string('owner_table', 80)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->string('status', 30)->default('submitted')->index();
            $table->jsonb('old_values');
            $table->jsonb('new_values');
            $table->text('reason');
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->foreignId('dokumen_id')->nullable()->constrained('dokumen')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['target_table', 'target_id']);
            $table->index(['owner_table', 'owner_id']);
            $table->index(['module', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_revisions');
    }
};
