<?php

declare(strict_types=1);

namespace App\Dto\Tax;

use App\Entity\Transaction;

/**
 * One line of the declaration: a category, its total for the year, and the movements behind it.
 *
 * $total comes from the database aggregate, never from re-adding $transactions: a single
 * source per figure is what makes the page checkable instead of merely plausible.
 */
final readonly class TaxCategoryLineDto
{
    public function __construct(
        public string $categoryName,
        /** Total in the space currency. */
        public string $total,
        public int $transactionCount,
        /** @var Transaction[] the movements this total is made of, oldest first */
        public array $transactions,
    ) {}
}
