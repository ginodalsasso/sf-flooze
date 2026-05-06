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
     */
    public function save(Transaction $transaction): void
    {
        $this->applyBalance($transaction->getAccount(), $transaction->getType(), (float) $transaction->getAmount());

        if ($transaction->getType() === TransactionTypeEnum::Transfer && $transaction->getDestinationAccount() !== null) {
            $this->applyBalance($transaction->getDestinationAccount(), TransactionTypeEnum::Income, (float) $transaction->getAmount());
        }

        $this->em->persist($transaction);
        $this->em->flush();
    }

    /**
     * Update an edited transaction: reverse old balance effect, apply new one.
     *
     * @param Account              $oldAccount      Account before edit
     * @param TransactionTypeEnum  $oldType         Type before edit
     * @param string               $oldAmount       Amount before edit
     * @param Account|null         $oldDestAccount  Destination account before edit (transfers)
     */
    public function update(
        Transaction $transaction,
        Account $oldAccount,
        TransactionTypeEnum $oldType,
        string $oldAmount,
        ?Account $oldDestAccount,
    ): void {
        // Reverse old effect
        $this->applyBalance($oldAccount, $oldType, -(float) $oldAmount);
        if ($oldType === TransactionTypeEnum::Transfer && $oldDestAccount !== null) {
            $this->applyBalance($oldDestAccount, TransactionTypeEnum::Income, -(float) $oldAmount);
        }

        // Apply new effect
        $this->applyBalance($transaction->getAccount(), $transaction->getType(), (float) $transaction->getAmount());
        if ($transaction->getType() === TransactionTypeEnum::Transfer && $transaction->getDestinationAccount() !== null) {
            $this->applyBalance($transaction->getDestinationAccount(), TransactionTypeEnum::Income, (float) $transaction->getAmount());
        }

        $this->em->flush();
    }

    /**
     * Soft-delete a transaction and reverse its balance effect.
     */
    public function delete(Transaction $transaction): void
    {
        $this->applyBalance($transaction->getAccount(), $transaction->getType(), -(float) $transaction->getAmount());

        if ($transaction->getType() === TransactionTypeEnum::Transfer && $transaction->getDestinationAccount() !== null) {
            $this->applyBalance($transaction->getDestinationAccount(), TransactionTypeEnum::Income, -(float) $transaction->getAmount());
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
