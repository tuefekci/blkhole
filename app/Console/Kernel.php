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
        // $schedule->command('inspire')->hourly();
        $schedule->command('poll:blackhole')->everyFifteenSeconds();
        $schedule->command('log:clear')->daily();
        $schedule->command('cache:clear')->daily();
        $schedule->command('queue:flush')->daily();
        $schedule->command('queue:prune-batches')->daily();
        $schedule->command('telescope:clear')->hourly();
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
