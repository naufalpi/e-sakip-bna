<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dpa_opd_items', function (Blueprint $table): void {
            $table->decimal('alokasi_tahun_sebelumnya', 20, 2)->default(0)->after('jenis_belanja');
            $table->decimal('alokasi_tahun_berikutnya', 20, 2)->default(0)->after('pagu_dpa');
        });

        DB::table('dpa_opd_items')
            ->whereNotNull('rka_opd_item_id')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $rkaItems = DB::table('rka_opd_items')
                    ->whereIn('id', $items->pluck('rka_opd_item_id')->filter()->unique())
                    ->get(['id', 'alokasi_tahun_sebelumnya', 'alokasi_tahun_berikutnya'])
                    ->keyBy('id');

                foreach ($items as $item) {
                    $rkaItem = $rkaItems->get($item->rka_opd_item_id);
                    if (! $rkaItem) {
                        continue;
                    }

                    DB::table('dpa_opd_items')->where('id', $item->id)->update([
                        'alokasi_tahun_sebelumnya' => $rkaItem->alokasi_tahun_sebelumnya,
                        'alokasi_tahun_berikutnya' => $rkaItem->alokasi_tahun_berikutnya,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('dpa_opd_items', function (Blueprint $table): void {
            $table->dropColumn(['alokasi_tahun_sebelumnya', 'alokasi_tahun_berikutnya']);
        });
    }
};
