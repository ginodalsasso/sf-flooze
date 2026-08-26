<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Dto\DateRangeDto;
use App\Enum\PeriodEnum;
use App\Service\Date\Contract\DateFormatterInterface;
use Symfony\Component\Clock\ClockInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Exposes the app's date formats and period presets to templates.
 *
 * Templates state intent (short, long, time) instead of repeating a format string, so a format
 * changes in DateFormatter alone. periods() keeps the quick filters off strtotime in Twig.
 */
final class DateExtension extends AbstractExtension
{
    public function __construct(
        private readonly DateFormatterInterface $dateFormatter,
        private readonly ClockInterface $clock,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('date_short', [$this->dateFormatter, 'short']),
            new TwigFilter('date_long', [$this->dateFormatter, 'long']),
            new TwigFilter('date_time', [$this->dateFormatter, 'dateTime']),
            new TwigFilter('time_short', [$this->dateFormatter, 'time']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('today', [$this, 'today']),
            new TwigFunction('periods', [$this, 'periods']),
        ];
    }

    public function today(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    /**
     * Quick filter presets, as the inclusive Y-m-d bounds the links carry.
     *
     * @return list<array{value: string, label: string, from: string, to: string}>
     */
    public function periods(): array
    {
        $now = $this->clock->now();
        $presets = [];

        foreach (PeriodEnum::cases() as $period) {
            $range = $period->range($now);

            $presets[] = [
                'value' => $period->value,
                'label' => $period->label(),
                'from'  => $range->from->format(DateRangeDto::INPUT_FORMAT),
                'to'    => $range->toInclusive()->format(DateRangeDto::INPUT_FORMAT),
            ];
        }

        return $presets;
    }
}
