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

        $this->applyBalance($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());

        if ($transaction->getType() === TransactionTypeEnum::TRANSFER && $transaction->getDestinationAccount() !== null) {
            $this->applyBalance($transaction->getDestinationAccount(), TransactionTypeEnum::INCOME, $transaction->getAmount());
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
        $this->applyBalance($oldAccount, $oldType, $this->negate($oldAmount));
        if ($oldType === TransactionTypeEnum::TRANSFER && $oldDestAccount !== null) {
            $this->applyBalance($oldDestAccount, TransactionTypeEnum::INCOME, $this->negate($oldAmount));
        }

        // Apply new data
        $this->applyFromDto($transaction, $input);

        // Ensure the new source account has enough available funds before applying the new effect.
        $this->guardSpendableFunds($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());
        $this->guardValidTransfer($transaction->getAccount(), $transaction->getDestinationAccount(), $transaction->getType());

        // Apply new effect
        $this->applyBalance($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());
        if ($transaction->getType() === TransactionTypeEnum::TRANSFER && $transaction->getDestinationAccount() !== null) {
            $this->applyBalance($transaction->getDestinationAccount(), TransactionTypeEnum::INCOME, $transaction->getAmount());
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

        if ($type === TransactionTypeEnum::TRANSFER && $destAccount !== null) {
            $this->applyBalance($destAccount, TransactionTypeEnum::INCOME, $this->negate($amount));
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

    private function guardSpendableFunds(Account $account, TransactionTypeEnum $type, string $amount): void
    {
        if ($type !== TransactionTypeEnum::INCOME) {
            $this->accountBalanceService->guardAvailableFunds($account, $amount);
        }
    }

    private function guardValidTransfer(?Account $source, ?Account $destination, TransactionTypeEnum $type): void
    {
        if ($type !== TransactionTypeEnum::TRANSFER || $destination === null) {
            return;
        }

        if ($source !== null && $source->getId() === $destination->getId()) {
            throw new \InvalidArgumentException('Le compte destinataire doit être différent du compte source.');
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
