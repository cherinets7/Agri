<?php

namespace App\Enums;

enum PerformanceBand: string
{
    case Low = 'low';
    case Acceptable = 'acceptable';
    case Target = 'target';
    case Over = 'over';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'ዝቅተኛ — ማስጠንቀቂያ',
            self::Acceptable => 'ተቀባይነት ያለው',
            self::Target => 'ግብ ላይ ደርሷል',
            self::Over => 'ከመጠን በላይ — ማረጋገጥ ይገባል',
            self::None => 'ሪፖርት የለም',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Low => 'red',
            self::Acceptable => 'amber',
            self::Target => 'green',
            self::Over => 'blue',
            self::None => 'gray',
        };
    }
}
