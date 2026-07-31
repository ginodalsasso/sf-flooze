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
        private readonly ExchangeRateService $exchangeRateService,
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
        $this->guardValidTransfer($transaction);

        $this->applyBalanceEffect($transaction);

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

        $this->reverseBalanceEffect($transaction);

        $this->applyFromDto($transaction, $input);

        // Ensure the new source account has enough available funds before applying the new effect.
        $this->guardSpendableFunds($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());
        $this->guardValidTransfer($transaction);

        $this->applyBalanceEffect($transaction);

        $this->em->flush();
    }

    /**
     * Soft-delete a transaction and reverse its balance effect.
     */
    public function delete(Transaction $transaction): void
    {
        $this->guardNotLinkedToAsset($transaction);

        $this->reverseBalanceEffect($transaction);

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
            ->setFxRate($this->exchangeRateService->getRate($input->account->getCurrency(), $input->space->getCurrency()))
            ->setDestinationAmount($this->resolveDestinationAmount($input))
            ->setDate($input->date)
            ->setDescription($input->description)
            ->setCategory($input->category);
    }

    /** A credited amount is only meaningful on a transfer crossing two currencies. */
    private function resolveDestinationAmount(TransactionInputDto $input): ?string
    {

        if ($input->type !== TransactionTypeEnum::TRANSFER || $input->destinationAccount === null) {
            return null;
        }

        $accountCurrency = $input->account->getCurrency();
        $destinationCurrency = $input->destinationAccount->getCurrency();
        $convertedAmount = $this->exchangeRateService->convert($input->amount, $accountCurrency, $destinationCurrency);

        return $accountCurrency === $destinationCurrency
            ? null
            : $convertedAmount;
    }

    /** Debit the source account and, on a transfer, credit the destination with what it actually receives. */
    private function applyBalanceEffect(Transaction $transaction): void
    {
        $transaction->getAccount()->applyOperation($transaction->getType(), $transaction->getAmount());

        if ($transaction->getType() === TransactionTypeEnum::TRANSFER && $transaction->getDestinationAccount() !== null) {
            $transaction->getDestinationAccount()->applyOperation(TransactionTypeEnum::INCOME, $transaction->getCreditedAmount());
        }
    }

    private function reverseBalanceEffect(Transaction $transaction): void
    {
        $transaction->getAccount()->reverseOperation($transaction->getType(), $transaction->getAmount());

        if ($transaction->getType() === TransactionTypeEnum::TRANSFER && $transaction->getDestinationAccount() !== null) {
            $transaction->getDestinationAccount()->reverseOperation(TransactionTypeEnum::INCOME, $transaction->getCreditedAmount());
        }
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
     * Guard that the transfer targets another account and, across currencies, states what was credited.
     */
    private function guardValidTransfer(Transaction $transaction): void
    {
        $destination = $transaction->getDestinationAccount();

        if ($transaction->getType() !== TransactionTypeEnum::TRANSFER || $destination === null) {
            return;
        }

        if ($transaction->getAccount()->getId() === $destination->getId()) {
            throw new \InvalidArgumentException('Le compte destinataire doit être différent du compte source.');
        }

        if ($destination->getCurrency() === $transaction->getAccount()->getCurrency()) {
            return;
        }

        $credited = $transaction->getDestinationAmount();

        if ($credited === null || bccomp($credited, '0', 2) <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'Virement entre devises différentes : le montant crédité en %s est obligatoire et doit être supérieur à 0.',
                $destination->getCurrency()->value,
            ));
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
