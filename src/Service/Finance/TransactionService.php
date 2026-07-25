<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\TransactionInputDto;
use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use Doctrine\ORM\EntityManagerInterface;

class TransactionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    /**
     * Persist a new transaction and update account balance(s).
     * Rejects non-positive amounts as a defense-in-depth measure.
     *
     * @throws \InvalidArgumentException if amount is not strictly positive or if funds are insufficient
     */
    public function save(TransactionInputDto $input): Transaction
    {
        $this->guardStrictlyPositive($input->amount);

        $transaction = new Transaction();
        $this->applyFromDto($transaction, $input);

        $this->guardSpendableFunds($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());
        $this->guardValidTransfer($transaction->getAccount(), $transaction->getDestinationAccount(), $transaction->getType());

        $transaction->getAccount()->applyOperation($transaction->getType(), $transaction->getAmount());

        if ($transaction->getType() === TransactionTypeEnum::TRANSFER && $transaction->getDestinationAccount() !== null) {
            $transaction->getDestinationAccount()->applyOperation(TransactionTypeEnum::INCOME, $transaction->getAmount());
        }

        $this->em->persist($transaction);
        $this->em->flush();

        return $transaction;
    }

    /**
     * Update an edited transaction: reverse old balance effect, apply new one.
     * Rejects non-positive amounts as a defense-in-depth measure.
     *
     * @throws \InvalidArgumentException if new amount is not strictly positive
     */
    public function update(Transaction $transaction, TransactionInputDto $input): void
    {
        $this->guardNotLinkedToAsset($transaction);
        $this->guardStrictlyPositive($input->amount);

        // Snapshot old state before applying the DTO.
        $oldAccount = $transaction->getAccount();
        $oldType = $transaction->getType();
        $oldAmount = $transaction->getAmount();
        $oldDestAccount = $transaction->getDestinationAccount();

        // Reverse old effect
        $oldAccount->reverseOperation($oldType, $oldAmount);
        if ($oldType === TransactionTypeEnum::TRANSFER && $oldDestAccount !== null) {
            $oldDestAccount->reverseOperation(TransactionTypeEnum::INCOME, $oldAmount);
        }

        // Apply new data
        $this->applyFromDto($transaction, $input);

        // Ensure the new source account has enough available funds before applying the new effect.
        $this->guardSpendableFunds($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());
        $this->guardValidTransfer($transaction->getAccount(), $transaction->getDestinationAccount(), $transaction->getType());

        // Apply new effect
        $transaction->getAccount()->applyOperation($transaction->getType(), $transaction->getAmount());
        if ($transaction->getType() === TransactionTypeEnum::TRANSFER && $transaction->getDestinationAccount() !== null) {
            $transaction->getDestinationAccount()->applyOperation(TransactionTypeEnum::INCOME, $transaction->getAmount());
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

        $transaction->getAccount()->reverseOperation($type, $amount);

        if ($type === TransactionTypeEnum::TRANSFER && $destAccount !== null) {
            $destAccount->reverseOperation(TransactionTypeEnum::INCOME, $amount);
        }

        $transaction->softDelete();
        $this->em->flush();
    }

    private function applyFromDto(Transaction $transaction, TransactionInputDto $input): void
    {
        $transaction
            ->setSpace($input->space)
            ->setAccount($input->account)
            ->setDestinationAccount($input->destinationAccount)
            ->setType($input->type)
            ->setAmount($input->amount)
            ->setDate($input->date)
            ->setDescription($input->description)
            ->setCategory($input->category);
    }

    /**
     * Guard that a numeric string is strictly positive, throwing an exception if not.
     */
    private function guardStrictlyPositive(string $amount): void
    {
        // bccomp: compare numeric strings (-1 if less, 0 if equal, 1 if greater).
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Transaction amount must be strictly positive.');
        }
    }

    /**
     * Guard that the account has sufficient available funds for the transaction.
     * Income transactions are exempt from this check.
     */
    private function guardSpendableFunds(Account $account, TransactionTypeEnum $type, string $amount): void
    {
        if ($type !== TransactionTypeEnum::INCOME) {
            $this->accountBalanceService->guardAvailableFunds($account, $amount);
        }
    }

    /**
     * Guard that the transfer is valid (source and destination accounts are different).
     */
    private function guardValidTransfer(?Account $source, ?Account $destination, TransactionTypeEnum $type): void
    {
        if ($type !== TransactionTypeEnum::TRANSFER || $destination === null) {
            return;
        }

        if ($source !== null && $source->getId() === $destination->getId()) {
            throw new \InvalidArgumentException('Le compte destinataire doit être différent du compte source.');
        }
    }

    /**
     * Guard that a transaction is not linked to an asset entry, throwing an exception if it is.
     */
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
