<?php

namespace App\Services\Notifications;

use App\Models\RekomendasiEvaluasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RekomendasiDeadlineReminderService
{
    public function __construct(private readonly SakipNotificationService $notificationService) {}

    public function send(int $days = 7): int
    {
        $today = now()->toDateString();
        $until = now()->addDays($days)->toDateString();
        $total = 0;

        RekomendasiEvaluasi::query()
            ->with(['opd:id,nama,singkatan', 'evaluasiSakip:id,tahun'])
            ->whereIn('status_tindak_lanjut', ['belum', 'proses', 'perlu_perbaikan'])
            ->whereNotNull('target_tanggal')
            ->whereDate('target_tanggal', '<=', $until)
            ->orderBy('target_tanggal')
            ->chunkById(100, function ($rekomendasiRows) use (&$total, $today, $days) {
                foreach ($rekomendasiRows as $rekomendasi) {
                    $isOverdue = $rekomendasi->target_tanggal?->toDateString() < $today;
                    $opdName = $rekomendasi->opd?->singkatan ?: ($rekomendasi->opd?->nama ?: 'OPD');
                    $title = $isOverdue
                        ? 'Tindak lanjut rekomendasi melewati target'
                        : 'Pengingat deadline tindak lanjut rekomendasi';
                    $message = $isOverdue
                        ? "Rekomendasi {$opdName} sudah melewati target tanggal {$rekomendasi->target_tanggal?->toDateString()}."
                        : "Rekomendasi {$opdName} akan jatuh tempo dalam {$days} hari atau kurang.";

                    $recipients = User::query()
                        ->where('status', 'active')
                        ->where(function (Builder $query) use ($rekomendasi) {
                            $query->where(fn (Builder $query) => $query
                                ->where('opd_id', $rekomendasi->opd_id)
                                ->whereHas('roles', fn (Builder $query) => $query->where('name', 'admin_opd')))
                                ->orWhereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                                    'admin_kabupaten_inspektorat',
                                    'admin_kabupaten_bagian_organisasi',
                                ]));
                        })
                        ->get();

                    $total += $this->notificationService->notify(
                        $recipients,
                        $isOverdue ? 'rekomendasi_overdue' : 'rekomendasi_deadline',
                        $title,
                        $message,
                        [
                            'dedupe_key' => 'rekomendasi:'.$rekomendasi->id.':'.($isOverdue ? 'overdue' : 'deadline').':'.$rekomendasi->target_tanggal?->toDateString(),
                            'rekomendasi_id' => $rekomendasi->id,
                            'opd_id' => $rekomendasi->opd_id,
                            'target_tanggal' => $rekomendasi->target_tanggal?->toDateString(),
                            'status_tindak_lanjut' => $rekomendasi->status_tindak_lanjut,
                            'tahun' => $rekomendasi->evaluasiSakip?->tahun,
                        ],
                        route('dashboard', ['tahun' => $rekomendasi->evaluasiSakip?->tahun ?: now()->year, 'opd_id' => $rekomendasi->opd_id]),
                    );
                }
            });

        return $total;
    }
}
