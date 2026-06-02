<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\Notifications\RekomendasiDeadlineReminderService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sakip:remind-rekomendasi-deadlines {--days= : Jumlah hari sebelum target tindak lanjut}', function () {
    $service = app(RekomendasiDeadlineReminderService::class);
    $days = (int) ($this->option('days') ?? config('sakip.notifications.deadline_reminder_days', 7));
    $sent = $service->send(max(0, $days));

    $this->info("{$sent} notifikasi deadline rekomendasi dibuat.");
})->purpose('Kirim notifikasi reminder deadline tindak lanjut rekomendasi SAKIP');
