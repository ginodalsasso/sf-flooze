<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;

/**
 * Input DTO for creating or editing a manual transaction.
 *
 * It decouples the form from the Transaction entity and lets the service decide
 * how to map the data (especially for balance updates).
 */
class TransactionInputDto
{
    public ?int $id = null;
    public TransactionTypeEnum $type;
    public Account $account;
    public ?Account $destinationAccount = null;
    public string $amount;
    public \DateTimeImmutable $date;
    public ?string $description = null;
    public ?Category $category = null;
    public Space $space;

    public static function fromTransaction(Transaction $transaction, Space $space): self
    {
        $dto = new self();
        $dto->id = $transaction->getId();
        $dto->type = $transaction->getType();
        $dto->account = $transaction->getAccount();
        $dto->destinationAccount = $transaction->getDestinationAccount();
        $dto->amount = $transaction->getAmount();
        $dto->date = $transaction->getDate();
        $dto->description = $transaction->getDescription();
        $dto->category = $transaction->getCategory();
        $dto->space = $space;

        return $dto;
    }
}
