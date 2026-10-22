<?php

use App\Console\Commands\CheckUpcomingMeetings;
use Illuminate\Support\Facades\Schedule;

// Cek pertemuan online yang akan datang tiap menit → kirim reminder H-1 & H-15m
Schedule::command(CheckUpcomingMeetings::class)->everyMinute()->withoutOverlapping();

// Auto-expire pengumuman yang tanggal_berakhir-nya sudah lewat (tiap hari jam 00:05)
Schedule::call(function () {
    \App\Models\Pengumuman::where('status_publish', 'published')
        ->whereDate('tanggal_berakhir', '<', now()->toDateString())
        ->update(['status_publish' => 'expired']);
})->dailyAt('00:05');
