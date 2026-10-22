<?php

namespace App\Console\Commands;

use App\Jobs\SendPertemuanReminderJob;
use App\Models\PertemuanOnline;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Dijalankan setiap menit oleh scheduler (routes/console.php):
 *   Schedule::command(CheckUpcomingMeetings::class)->everyMinute();
 * Mengirim reminder H-1 (sekali per pertemuan) dan H-15 menit (sekali per pertemuan).
 */
class CheckUpcomingMeetings extends Command
{
    protected $signature = 'meetings:check-upcoming';
    protected $description = 'Kirim reminder pertemuan online H-1 dan 15 menit sebelum mulai';

    public function handle(): int
    {
        $now = Carbon::now();

        $this->kirimReminder(
            $now,
            windowStart: $now->copy()->addDay(),
            windowEnd: $now->copy()->addDay()->addMinutes(5),
            column: 'reminder_h1_sent',
        );

        $this->kirimReminder(
            $now,
            windowStart: $now->copy()->addMinutes(15),
            windowEnd: $now->copy()->addMinutes(20),
            column: 'reminder_15m_sent',
        );

        return self::SUCCESS;
    }

    private function kirimReminder(Carbon $now, Carbon $windowStart, Carbon $windowEnd, string $column): void
    {
        PertemuanOnline::where('status', 'terjadwal')
            ->where($column, false)
            ->whereDate('tanggal', $windowStart->toDateString())
            ->get()
            ->each(function (PertemuanOnline $pertemuan) use ($windowStart, $windowEnd, $column) {
                $waktuMulai = $pertemuan->waktuMulai();

                if ($waktuMulai->betweenIncluded($windowStart, $windowEnd)) {
                    SendPertemuanReminderJob::dispatch($pertemuan);
                    $pertemuan->update([$column => true]);
                }
            });
    }
}
