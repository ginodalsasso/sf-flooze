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
     * Rejects non-positive amounts as a defense-in-depth measure.
     *
     * @throws \InvalidArgumentException if amount is not strictly positive
     */
    public function save(Transaction $transaction): void
    {
        $amount = $transaction->getAmount();
        $this->guardStrictlyPositive($amount);

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
        $this->guardNotLinkedToAsset($transaction);

        $amount = $transaction->getAmount();
        $this->guardStrictlyPositive($amount);

        // Reverse old effect
        $this->applyBalance($oldAccount, $oldType, $this->negate($oldAmount));
        if ($oldType === TransactionTypeEnum::Transfer && $oldDestAccount !== null) {
            $this->applyBalance($oldDestAccount, TransactionTypeEnum::Income, $this->negate($oldAmount));
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
        $this->guardNotLinkedToAsset($transaction);

        $type = $transaction->getType();
        $destAccount = $transaction->getDestinationAccount();
        $amount = $transaction->getAmount();

        $this->applyBalance($transaction->getAccount(), $type, $this->negate($amount));

        if ($type === TransactionTypeEnum::Transfer && $destAccount !== null) {
            $this->applyBalance($destAccount, TransactionTypeEnum::Income, $this->negate($amount));
        }

        $transaction->softDelete();
        $this->em->flush();
    }

    private function applyBalance(Account $account, TransactionTypeEnum $type, string $amount): void
    {
        // bcmul: multiply numeric strings, scale 2 keeps cents precision.
        $delta = bcmul($amount, (string) $type->balanceSign(), 2);
        $account->adjustBalance($delta);
    }

    private function negate(string $amount): string
    {
        // Multiply by -1 to flip the sign without float rounding.
        return bcmul('-1', $amount, 2);
    }

    private function guardStrictlyPositive(string $amount): void
    {
        // bccomp: compare numeric strings (-1 if less, 0 if equal, 1 if greater).
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Transaction amount must be strictly positive.');
        }
    }

    private function guardNotLinkedToAsset(Transaction $transaction): void
    {
        if ($transaction->isLinkedToAsset()) {
            throw new \RuntimeException(sprintf(
                'Transaction %d is linked to asset "%s" and must be managed from the asset page.',
                $transaction->getId(),
                $transaction->getAssetEntry()->getAsset()->getTicker()
            ));
        }
    }
}
