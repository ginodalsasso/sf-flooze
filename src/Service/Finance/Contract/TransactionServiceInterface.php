<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\TransactionInputDto;
use App\Entity\Transaction;

/** Manual transaction lifecycle: persistence and account balance effects. */
interface TransactionServiceInterface
{
    /**
     * Persist a new transaction and update account balance(s).
     *
     * @throws \InvalidArgumentException if amount is not strictly positive or if funds are insufficient
     */
    public function save(TransactionInputDto $input): Transaction;

    /**
     * Update an edited transaction: reverse old balance effect, apply new one.
     *
     * @throws \InvalidArgumentException if new amount is not strictly positive
     * @throws \RuntimeException if the transaction is linked to an asset entry
     */
    public function update(Transaction $transaction, TransactionInputDto $input): void;

    /**
     * Soft-delete a transaction and reverse its balance effect.
     *
     * @throws \RuntimeException if the transaction is linked to an asset entry
     */
    public function delete(Transaction $transaction): void;
}
