<?php

declare(strict_types=1);

namespace App\Service\Date;

use App\Service\Date\Contract\DateFormatterInterface;

/**
 * Renders dates with ICU patterns rather than date() so month and day names are localised.
 *
 * The locale is explicit and not kernel.default_locale: the UI is written in French while the
 * framework locale stays 'en' for validator messages.
 */
final class DateFormatter implements DateFormatterInterface
{
    private const SHORT = 'dd/MM/y';
    private const LONG = 'EEEE d MMMM y';
    private const DATE_TIME = "dd/MM/y 'à' HH:mm";
    private const TIME = 'HH:mm';

    /** @var array<string, \IntlDateFormatter> */
    private array $formatters = [];

    public function __construct(
        private readonly string $locale,
        private readonly string $timezone,
    ) {}

    public function short(\DateTimeInterface $date): string
    {
        return $this->render(self::SHORT, $date);
    }

    public function long(\DateTimeInterface $date): string
    {
        return $this->render(self::LONG, $date);
    }

    public function dateTime(\DateTimeInterface $date): string
    {
        return $this->render(self::DATE_TIME, $date);
    }

    public function time(\DateTimeInterface $date): string
    {
        return $this->render(self::TIME, $date);
    }

    private function render(string $pattern, \DateTimeInterface $date): string
    {
        $this->formatters[$pattern] ??= new \IntlDateFormatter(
            $this->locale,
            dateType: \IntlDateFormatter::NONE,
            timeType: \IntlDateFormatter::NONE,
            timezone: new \DateTimeZone($this->timezone),
            calendar: null,
            pattern: $pattern,
        ); 

        return $this->formatters[$pattern]->format($date);
    }
}
