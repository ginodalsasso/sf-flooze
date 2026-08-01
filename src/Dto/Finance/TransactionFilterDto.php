<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Entity\Account;
use App\Entity\Category;
use App\Enum\TransactionTypeEnum;

/**
 * Search criteria for the transaction list.
 *
 * Every field is optional: a null one is simply not applied, so an empty DTO
 * returns the whole list.
 */
class TransactionFilterDto
{
    /** Free text matched against the description, the category and both account names. */
    public ?string $query = null;
    public ?TransactionTypeEnum $type = null;
    public ?Account $account = null;
    public ?Category $category = null;
    public ?\DateTimeImmutable $dateFrom = null;
    public ?\DateTimeImmutable $dateTo = null;

    /** Bounds compared against the amount converted to the space currency, accounts holding different ones. */
    public ?string $amountMin = null;
    public ?string $amountMax = null;

    /** True when the account is what the page is about rather than something the user picked. */
    public bool $accountIsScope = false;

    public static function forAccount(Account $account): self
    {
        $filter = new self();
        $filter->account = $account;
        $filter->accountIsScope = true;

        return $filter;
    }

    /** Criteria of the advanced panel the user set, shown as a badge next to its summary. */
    public function countAdvancedCriteria(): int
    {
        return count(array_filter([
            $this->accountIsScope ? null : $this->account,
            $this->category,
            $this->dateFrom,
            $this->dateTo,
            $this->amountMin,
            $this->amountMax,
        ], fn(mixed $criterion) => $criterion !== null));
    }

    /** True when a criterion other than the type is set: the advanced panel then opens on load. */
    public function hasAdvancedCriteria(): bool
    {
        return $this->countAdvancedCriteria() > 0;
    }

    public function isEmpty(): bool
    {
        return $this->type === null
            && ($this->query === null || $this->query === '')
            && !$this->hasAdvancedCriteria();
    }
}
