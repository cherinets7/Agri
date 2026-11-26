<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RemindMissingReportsCommand extends Command
{
    protected $signature = 'agri:remind-missing {--date= : Gregorian date YYYY-MM-DD (defaults to last work day)} {--no-weekly : Skip weekly woreda reminders} {--no-email : Only create in-app notifications}';

    protected $description = 'Send in-app and email reminders for missing daily and weekly agricultural reports.';

    public function handle(ReminderService $service): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : null;
        $result = $service->sendMissingReminders(
            actor: null,
            day: $day,
            includeWeekly: ! $this->option('no-weekly'),
            sendEmail: ! $this->option('no-email'),
        );

        $this->info("Reminders sent for {$result['date']}: daily={$result['daily_sent']}, weekly={$result['weekly_sent']}.");

        return self::SUCCESS;
    }
}
