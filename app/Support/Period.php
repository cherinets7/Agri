<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class Period
{
    public function __construct(public readonly Carbon $from, public readonly Carbon $to, public readonly string $preset) {}

    public static function fromRequest(Request $request, string $default = 'week'): self
    {
        $today = Carbon::today();

        $preset = (string) $request->query('range', '');
        if ($preset === '' && ! $request->filled('from') && ! $request->filled('to')) {
            $preset = $default;
        }

        switch ($preset) {
            case 'today':
                return new self($today->copy(), $today->copy(), 'today');
            case 'month':
                return new self($today->copy()->startOfMonth(), $today->copy(), 'month');
            case 'last30':
                return new self($today->copy()->subDays(29), $today->copy(), 'last30');
            case 'week':
                return new self($today->copy()->startOfWeek(CarbonInterface::MONDAY), $today->copy(), 'week');
        }

        $from = self::parse($request->query('from')) ?? $today->copy()->startOfWeek(CarbonInterface::MONDAY);
        $to = self::parse($request->query('to')) ?? $today->copy();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return new self($from, $to, 'custom');
    }

    private static function parse(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function label(): string
    {
        return EthiopianDate::format($this->from).' – '.EthiopianDate::format($this->to);
    }

    public function query(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
