<?php

namespace Database\Seeders;

use App\Models\BidangUrusan;
use App\Models\Opd;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BidangUrusanOpdPengampuSeeder extends Seeder
{
    public function run(): void
    {
        $bidangIds = BidangUrusan::query()->pluck('id', 'kode');

        Opd::query()
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'kode'])
            ->each(function (Opd $opd) use ($bidangIds): void {
                foreach ($this->bidangCodesFromOpdCode($opd->kode) as $kode) {
                    $bidangId = $bidangIds[$kode] ?? null;

                    if (! $bidangId) {
                        continue;
                    }

                    DB::table('bidang_urusan_opd_pengampu')->updateOrInsert(
                        [
                            'bidang_urusan_id' => $bidangId,
                            'opd_id' => $opd->id,
                            'peran' => 'pengampu_urusan',
                        ],
                        [
                            'is_utama' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function bidangCodesFromOpdCode(string $opdCode): array
    {
        $parts = explode('.', $opdCode);
        $codes = [];

        for ($index = 0; $index + 1 < count($parts); $index += 2) {
            $major = trim((string) $parts[$index]);
            $minor = trim((string) $parts[$index + 1]);

            if ($major === '0' || $minor === '00') {
                continue;
            }

            $codes[] = "{$major}.{$minor}";
        }

        return array_values(array_unique($codes));
    }
}
