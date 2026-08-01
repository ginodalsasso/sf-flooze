<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\TransactionInputDto;
use App\Entity\Transaction;

/** Manual transaction lifecycle: guards and persistence. Account balances derive from the stored rows. */
interface TransactionServiceInterface
{
    /**
     * @throws \InvalidArgumentException if amount is not strictly positive or if funds are insufficient
     */
    public function save(TransactionInputDto $input): Transaction;

    /**
     * @throws \InvalidArgumentException if new amount is not strictly positive
     * @throws \RuntimeException if the transaction is linked to an asset entry
     */
    public function update(Transaction $transaction, TransactionInputDto $input): void;

    /**
     * @throws \RuntimeException if the transaction is linked to an asset entry
     */
    public function delete(Transaction $transaction): void;
}
