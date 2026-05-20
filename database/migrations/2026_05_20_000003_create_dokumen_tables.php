<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->foreignId('periode_tahun_id')->nullable()->constrained('periode_tahun')->nullOnDelete();
            $table->string('jenis', 60)->index();
            $table->string('judul');
            $table->string('nomor_dokumen')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64)->index();
            $table->string('storage_disk', 60);
            $table->string('storage_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['opd_id', 'jenis']);
            $table->index(['periode_tahun_id', 'jenis']);
            $table->index(['jenis', 'status']);
            $table->index('uploaded_by');
        });

        Schema::create('dokumen_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->string('related_type');
            $table->unsignedBigInteger('related_id');
            $table->string('label')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->unique(['dokumen_id', 'related_type', 'related_id'], 'dokumen_relations_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_relations');
        Schema::dropIfExists('dokumen');
    }
};
