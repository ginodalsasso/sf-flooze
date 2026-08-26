<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * A half-open period [from, toExclusive[ — the convention every date aggregate uses.
 *
 * The exclusive upper bound removes the "is the last day counted?" question from every
 * query; toInclusive() converts it back for what a user reads (URLs, date pickers).
 */
final readonly class DateRangeDto
{
    /** Format of every date crossing the HTTP boundary: query params and <input type="date">. */
    public const string INPUT_FORMAT = 'Y-m-d';

    public function __construct(
        public \DateTimeImmutable $from, // inclusive = the first day counted
        public \DateTimeImmutable $toExclusive, // exclusive = the first day NOT counted 
    ) {}

    public function toInclusive(): \DateTimeImmutable
    {
        return $this->toExclusive->modify('-1 day');
    }

    /** Null on anything that is not a plain Y-m-d date, so a forged query param cannot break a page. */
    public static function parseDay(string $value): ?\DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!' . self::INPUT_FORMAT, $value) ?: null;
    }
}
