<?php

namespace App\Services\Dashboard;

use App\Models\Dokumen;
use App\Models\EvaluasiSakip;
use App\Models\EvaluasiSakipItem;
use App\Models\Lhe;
use App\Models\Lkjip;
use App\Models\Opd;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RealisasiKinerja;
use App\Models\RealisasiProgram;
use App\Models\RekomendasiEvaluasi;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\RenstraOpd;
use App\Models\PredikatEvaluasi;
use App\Models\ProgramRpjmdOpdPenanggungJawab;
use App\Models\Rpjmd;
use App\Models\TargetIndikatorOpdProgram;
use App\Models\TargetIndikatorProgramRpjmd;
use App\Models\TargetIndikatorSasaranDaerah;
use App\Models\TargetIndikatorSasaranOpd;
use App\Models\TargetIndikatorTujuanDaerah;
use App\Models\TargetIndikatorTujuanOpd;
use App\Models\TargetTriwulanIndikator;
use App\Models\TindakLanjutRekomendasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    private const VERSION_KEY = 'dashboard:cache-version';

    /**
     * @var array<int, class-string<Model>>
     */
    private const STRATEGIC_MODELS = [
        Opd::class,
        Rpjmd::class,
        ProgramRpjmdOpdPenanggungJawab::class,
        RenstraOpd::class,
        TargetIndikatorTujuanDaerah::class,
        TargetIndikatorSasaranDaerah::class,
        TargetIndikatorProgramRpjmd::class,
        TargetIndikatorTujuanOpd::class,
        TargetIndikatorSasaranOpd::class,
        TargetIndikatorOpdProgram::class,
        TargetTriwulanIndikator::class,
        PerjanjianKinerja::class,
        PerjanjianKinerjaItem::class,
        RencanaAksi::class,
        RencanaAksiItem::class,
        RealisasiKinerja::class,
        RealisasiProgram::class,
        Lkjip::class,
        EvaluasiSakip::class,
        EvaluasiSakipItem::class,
        Lhe::class,
        PredikatEvaluasi::class,
        RekomendasiEvaluasi::class,
        TindakLanjutRekomendasi::class,
        Dokumen::class,
    ];

    public function version(): int
    {
        return max(1, (int) Cache::get(self::VERSION_KEY, 1));
    }

    public function invalidate(): int
    {
        $version = $this->version() + 1;

        Cache::forever(self::VERSION_KEY, $version);

        return $version;
    }

    public function invalidateForModel(Model $model): void
    {
        foreach (self::STRATEGIC_MODELS as $class) {
            if ($model instanceof $class) {
                $this->invalidate();

                return;
            }
        }
    }
}
