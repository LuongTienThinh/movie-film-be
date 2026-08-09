<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:cron-job-update-films --queue --queue-name=film-updates')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('queue:work database --queue=film-updates --max-jobs=50 --stop-when-empty --tries=3 --timeout=600')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('cloud-assets:sync')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('queue:work database --queue=cloud-assets --max-jobs=50 --stop-when-empty --tries=3 --timeout=3600')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('cloud-assets:cleanup-drive-files')
            ->daily()
            ->withoutOverlapping();

        $schedule->command('cloud-assets:cleanup-local-files')
            ->daily()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
