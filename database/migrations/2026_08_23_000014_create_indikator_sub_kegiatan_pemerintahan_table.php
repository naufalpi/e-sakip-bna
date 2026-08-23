<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indikator_sub_kegiatan_pemerintahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_kegiatan_pemerintahan_id')
                ->constrained('sub_kegiatan_pemerintahan')
                ->cascadeOnDelete();
            $table->text('indikator');
            $table->foreignId('satuan_indikator_id')
                ->nullable()
                ->constrained('satuan_indikator')
                ->nullOnDelete();
            $table->boolean('is_utama')->default(false);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();

            $table->index(
                ['sub_kegiatan_pemerintahan_id', 'urutan'],
                'indikator_sub_kegiatan_master_urutan_index',
            );
        });

        $now = now();

        DB::table('sub_kegiatan_pemerintahan')
            ->whereNotNull('indikator_sub_kegiatan')
            ->orderBy('id')
            ->chunkById(500, function ($subKegiatanRows) use ($now): void {
                $rows = collect($subKegiatanRows)
                    ->filter(fn (object $row) => trim((string) $row->indikator_sub_kegiatan) !== '')
                    ->map(fn (object $row) => [
                        'sub_kegiatan_pemerintahan_id' => $row->id,
                        'indikator' => trim((string) $row->indikator_sub_kegiatan),
                        'satuan_indikator_id' => $row->satuan_indikator_id,
                        'is_utama' => true,
                        'urutan' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                if ($rows !== []) {
                    DB::table('indikator_sub_kegiatan_pemerintahan')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_sub_kegiatan_pemerintahan');
    }
};
