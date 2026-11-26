<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ReportStatusNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public string $kind = 'status_update',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
