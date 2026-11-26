<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Support\EthiopianDate;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissingReportNotification extends Notification
{
    public function __construct(
        public string $kind, // 'missing_daily' | 'missing_weekly'
        public CarbonInterface $targetDate,
        public string $locationName,
        public string $actionUrl,
        public bool $sendEmail = true,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->sendEmail && ! empty($notifiable->email) && config('extension.email_reminders_enabled', true)) {
            $channels[] = 'mail';
        }
        if (config('extension.sms_reminders_enabled') && ! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $et = EthiopianDate::format($this->targetDate);
        $subject = $this->kind === 'missing_weekly'
            ? "ማሳሰቢያ፦ ያልተላከ ሳምንታዊ ክንውን ሪፖርት ({$et})"
            : "ማሳሰቢያ፦ ያልተላከ ዕለታዊ ሪፖርት ({$et})";

        $body = $this->kind === 'missing_weekly'
            ? "ለ{$this->locationName} የ{$et} ሳምንታዊ ዕቅድ/ክንውን ሪፖርት እስካሁን አልተላከም። እባክዎ ገብተው ይላኩ።"
            : "ለ{$this->locationName} የ{$et} ዕለታዊ የኤክስቴንሽን ሪፖርት እስካሁን አልተላከም። እባክዎ ገብተው ይላኩ።";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("ሰላም {$notifiable->name}፣")
            ->line($body)
            ->action('ሪፖርት አስገባ', $this->actionUrl)
            ->salutation('የየም ዞን ግብርና ክትትል ሲስተም');
    }

    public function toSms(object $notifiable): string
    {
        $et = EthiopianDate::format($this->targetDate);

        return $this->kind === 'missing_weekly'
            ? "ማሳሰቢያ፦ ለ{$this->locationName} የ{$et} ሳምንታዊ ክንውን ሪፖርት አልተላከም።"
            : "ማሳሰቢያ፦ ለ{$this->locationName} የ{$et} ዕለታዊ ሪፖርት አልተላከም።";
    }

    public function toArray(object $notifiable): array
    {
        $et = EthiopianDate::format($this->targetDate);

        return [
            'kind' => $this->kind,
            'date' => $this->targetDate->toDateString(),
            'et_date' => $et,
            'title' => $this->kind === 'missing_weekly'
                ? "ያልተላከ ሳምንታዊ ክንውን ({$et})"
                : "ያልተላከ ዕለታዊ ሪፖርት ({$et})",
            'message' => $this->kind === 'missing_weekly'
                ? "{$this->locationName} — የ{$et} ሳምንት ክንውን ሪፖርት ገና አልተላከም።"
                : "{$this->locationName} — የ{$et} ዕለታዊ ሪፖርት ገና አልተላከም።",
            'url' => $this->actionUrl,
        ];
    }
}
