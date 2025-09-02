<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Definiujesz harmonogram zadań
     */
    protected function schedule(Schedule $schedule)
    {
        // Codziennie o 9:00 rano
        $schedule->command('visits:check-tomorrow')->dailyAt('09:00');
    }

    /**
     * Rejestracja komend Artisan
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}