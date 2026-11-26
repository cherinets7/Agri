<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;

class NullSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): bool
    {
        return false;
    }
}
