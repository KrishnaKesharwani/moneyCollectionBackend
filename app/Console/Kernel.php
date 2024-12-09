<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use App\Http\Controllers\ScheduleController;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $controller = new ExportController();
            $controller->deleteExportFiles();
        })->dailyAt('00:00'); // Runs daily at midnight

        //for set cron job
        //* * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1

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
