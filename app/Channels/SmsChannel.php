<?php

namespace App\Channels;

use App\Contracts\SmsGateway;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone ?? null;
        if (! $phone || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->gateway->send($phone, $notification->toSms($notifiable));
    }
}
