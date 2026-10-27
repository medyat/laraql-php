<?php

declare(strict_types=1);

namespace Medyat\Laraql\Query\Compiler;

use Carbon\CarbonImmutable;
use Medyat\Laraql\Context;

/**
 * Day arithmetic in the request timezone: presets become ISO day ranges,
 * local days become UTC instants for datetime columns. Mirrors the client's
 * preset rules (weeks start on Monday; next_7_days and last_30_days are
 * inclusive of both ends).
 */
final readonly class DateRange
{
    public const array PRESETS = ['today', 'this_week', 'next_7_days', 'this_month', 'last_30_days', 'this_quarter', 'overdue'];

    private CarbonImmutable $today;

    public function __construct(private Context $context)
    {
        $this->today = $context->now->setTimezone($context->timezone)->startOfDay();
    }

    /** @return array{0: string|null, 1: string|null} ISO days, both inclusive; null = open end */
    public function preset(string $preset): array
    {
        $t = $this->today;

        return match ($preset) {
            'today' => [$this->day($t), $this->day($t)],
            'this_week' => [$this->day($t->startOfWeek(CarbonImmutable::MONDAY)), $this->day($t->endOfWeek(CarbonImmutable::SUNDAY))],
            'next_7_days' => [$this->day($t), $this->day($t->addDays(7))],
            'this_month' => [$this->day($t->startOfMonth()), $this->day($t->endOfMonth())],
            'last_30_days' => [$this->day($t->subDays(30)), $this->day($t)],
            'this_quarter' => [$this->day($t->startOfQuarter()), $this->day($t->endOfQuarter())],
            'overdue' => [null, $this->day($t->subDay())],
            default => [null, null],
        };
    }

    /** The ISO day of a `before`/`after` value: a date string or `{ preset: today }`. */
    public function dayOf(mixed $value): string
    {
        return is_string($value) ? substr($value, 0, 10) : $this->day($this->today);
    }

    /** Start of the given local day as a UTC `Y-m-d H:i:s` instant. */
    public function startUtc(string $day): string
    {
        return CarbonImmutable::parse($day, $this->context->timezone)->startOfDay()->utc()->format('Y-m-d H:i:s');
    }

    /** Start of the day after the given local day (exclusive upper bound), UTC. */
    public function endUtcExclusive(string $day): string
    {
        return CarbonImmutable::parse($day, $this->context->timezone)->addDay()->startOfDay()->utc()->format('Y-m-d H:i:s');
    }

    public function nextDay(string $day): string
    {
        return CarbonImmutable::parse($day)->addDay()->format('Y-m-d');
    }

    private function day(CarbonImmutable $date): string
    {
        return $date->format('Y-m-d');
    }
}
