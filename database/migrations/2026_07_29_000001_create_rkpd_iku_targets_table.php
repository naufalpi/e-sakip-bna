<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkpd_iku_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkpd_id')->constrained('rkpd')->cascadeOnDelete();
            $table->foreignId('periode_tahun_id')->constrained('periode_tahun')->cascadeOnDelete();
            $table->string('indikator_type', 60);
            $table->unsignedBigInteger('indikator_id');
            $table->text('target_rkpd')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rkpd_id', 'indikator_type', 'indikator_id'], 'rkpd_iku_targets_unique');
            $table->index(['indikator_type', 'indikator_id']);
            $table->index(['rkpd_id', 'periode_tahun_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkpd_iku_targets');
    }
};
