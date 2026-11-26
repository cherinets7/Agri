<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use App\Models\WeeklyPlanReport;
use App\Notifications\MissingReportNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReminderService
{
    public function __construct(private ExtensionPerformance $perf) {}

    /**
     * Send in-app (and optional email) reminders to experts who missed a daily report.
     *
     * @return array{daily_sent:int, weekly_sent:int, date:string}
     */
    public function sendMissingReminders(?User $actor = null, ?CarbonInterface $day = null, bool $includeWeekly = true, bool $sendEmail = true): array
    {
        $day = $day ? Carbon::instance($day->copy())->startOfDay() : ExtensionPerformance::lastWorkDay();
        $viewer = $actor ?? User::query()->where('role', Role::Zone->value)->where('is_active', true)->first()
            ?? new User(['role' => Role::Zone]);

        $missingExperts = $this->perf->missingOn($viewer, $day);
        $dailySent = 0;

        foreach ($missingExperts as $expert) {
            if ($this->alreadySent($expert, 'missing_daily', $day->toDateString())) {
                continue;
            }

            $expert->notify(new MissingReportNotification(
                kind: 'missing_daily',
                targetDate: $day,
                locationName: $expert->location?->name_am ?? 'ቀበሌ',
                actionUrl: route('reports.create'),
                sendEmail: $sendEmail,
            ));
            $dailySent++;
        }

        $weeklySent = 0;
        if ($includeWeekly && ($viewer->isZone() || $actor === null)) {
            $lastWeek = PlanMatrix::weekStart(now()->subWeek());
            $submittedWoredas = WeeklyPlanReport::query()
                ->counted()
                ->whereDate('week_start', $lastWeek->toDateString())
                ->pluck('woreda_id');

            $missingWoredas = Location::query()
                ->ofLevel(Level::Woreda)
                ->whereNotIn('id', $submittedWoredas)
                ->get();

            foreach ($missingWoredas as $woreda) {
                $woredaUsers = User::query()
                    ->where('role', Role::Woreda->value)
                    ->where('location_id', $woreda->id)
                    ->where('is_active', true)
                    ->get();

                foreach ($woredaUsers as $wUser) {
                    if ($this->alreadySent($wUser, 'missing_weekly', $lastWeek->toDateString())) {
                        continue;
                    }

                    $wUser->notify(new MissingReportNotification(
                        kind: 'missing_weekly',
                        targetDate: $lastWeek,
                        locationName: $woreda->name_am,
                        actionUrl: route('weekly.create', ['week' => $lastWeek->toDateString()]),
                        sendEmail: $sendEmail,
                    ));
                    $weeklySent++;
                }
            }
        }

        return [
            'daily_sent' => $dailySent,
            'weekly_sent' => $weeklySent,
            'date' => $day->toDateString(),
        ];
    }

    private function alreadySent(User $user, string $kind, string $date): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('type', MissingReportNotification::class)
            ->get(['data'])
            ->contains(function ($row) use ($kind, $date) {
                $decoded = json_decode($row->data, true);

                return ($decoded['kind'] ?? null) === $kind && ($decoded['date'] ?? null) === $date;
            });
    }
}
