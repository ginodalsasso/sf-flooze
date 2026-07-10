<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use Doctrine\ORM\EntityManagerInterface;

class TransactionService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Persist a new transaction and update account balance(s).
     * Rejects negative amounts as a defense-in-depth measure.
     *
     * @throws \InvalidArgumentException if amount is not strictly positive
     */
    public function save(Transaction $transaction): void
    {
        $amount = (float) $transaction->getAmount();
        if ($amount <= 0.0) {
            throw new \InvalidArgumentException('Transaction amount must be strictly positive.');
        }

        $this->applyBalance($transaction->getAccount(), $transaction->getType(), $amount);

        if ($transaction->getType() === TransactionTypeEnum::Transfer && $transaction->getDestinationAccount() !== null) {
            $this->applyBalance($transaction->getDestinationAccount(), TransactionTypeEnum::Income, $amount);
        }

        $this->em->persist($transaction);
        $this->em->flush();
    }
    
    /**
     * Update an edited transaction: reverse old balance effect, apply new one.
     * Rejects non-positive amounts as a defense-in-depth measure.
     *
     * @param Account              $oldAccount      Account before edit
     * @param TransactionTypeEnum  $oldType         Type before edit
     * @param string               $oldAmount       Amount before edit
     * @param Account|null         $oldDestAccount  Destination account before edit (transfers)
     *
     * @throws \InvalidArgumentException if new amount is not strictly positive
     */
    public function update(
        Transaction $transaction,
        Account $oldAccount,
        TransactionTypeEnum $oldType,
        string $oldAmount,
        ?Account $oldDestAccount,
    ): void {
        $amount = (float) $transaction->getAmount();
        if ($amount <= 0.0) {
            throw new \InvalidArgumentException('Transaction amount must be strictly positive.');
        }

        // Reverse old effect
        $this->applyBalance($oldAccount, $oldType, -(float) $oldAmount);
        if ($oldType === TransactionTypeEnum::Transfer && $oldDestAccount !== null) {
            $this->applyBalance($oldDestAccount, TransactionTypeEnum::Income, -(float) $oldAmount);
        }

        $type = $transaction->getType();
        $destAccount = $transaction->getDestinationAccount();

        // Apply new effect
        $this->applyBalance($transaction->getAccount(), $type, $amount);
        if ($type === TransactionTypeEnum::Transfer && $destAccount !== null) {
            $this->applyBalance($destAccount, TransactionTypeEnum::Income, $amount);
        }

        $this->em->flush();
    }

    /**
     * Soft-delete a transaction and reverse its balance effect.
     */
    public function delete(Transaction $transaction): void
    {
        $type = $transaction->getType();
        $destAccount = $transaction->getDestinationAccount();
        $amount = (float) $transaction->getAmount();

        $this->applyBalance($transaction->getAccount(), $type, -$amount);

        if ($type === TransactionTypeEnum::Transfer && $destAccount !== null) {
            $this->applyBalance($destAccount, TransactionTypeEnum::Income, -$amount);
        }

        $transaction->softDelete();
        $this->em->flush();
    }

    private function applyBalance(Account $account, TransactionTypeEnum $type, float $amount): void
    {
        $delta = $amount * $type->balanceSign();
        $newBalance = (float) $account->getBalance() + $delta;
        $account->setBalance((string) round($newBalance, 2));
    }
}
