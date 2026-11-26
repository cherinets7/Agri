<?php

namespace App\Contracts;

/**
 * Minimal SMS port. No paid gateway is wired: the default `log` driver writes messages to the
 * application log, `null` discards them. Add a driver class (e.g. Ethio Telecom / Africa's Talking)
 * and select it with SMS_DRIVER once the zone has a contract and sender id.
 */
interface SmsGateway
{
    /** @return bool true when the message was accepted by the gateway */
    public function send(string $phone, string $message): bool;
}
