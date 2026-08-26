<?php

declare(strict_types=1);

namespace App\Service\Date\Contract;

/**
 * Single source of the date formats the app displays.
 *
 * Twig reads it through DateExtension, JSON endpoints inject it directly: a date rendered
 * server-side and the same date rendered by a Stimulus controller cannot drift apart.
 */
interface DateFormatterInterface
{
    /** 24/08/2026 — lists, tables, anywhere a date is a data point. */
    public function short(\DateTimeInterface $date): string;

    /** lundi 24 août 2026 — headings, where the date is read as a sentence. */
    public function long(\DateTimeInterface $date): string;

    /** 24/08/2026 à 10:21 — when the time of day carries meaning (quotes, readings). */
    public function dateTime(\DateTimeInterface $date): string;

    /** 10:21 — same day implied by the surrounding text. */
    public function time(\DateTimeInterface $date): string;
}
