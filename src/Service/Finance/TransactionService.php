<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\TransactionInputDto;
use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use App\Service\Finance\Contract\AccountBalanceServiceInterface;
use App\Service\Finance\Contract\ExchangeRateServiceInterface;
use App\Service\Finance\Contract\TransactionServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class TransactionService implements TransactionServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountBalanceServiceInterface $accountBalanceService,
        private readonly ExchangeRateServiceInterface $exchangeRateService,
    ) {}

    /** Rejects non-positive amounts as a defense-in-depth measure. */
    public function save(TransactionInputDto $input): Transaction
    {
        $this->guardStrictlyPositive($input->amount);

        $transaction = new Transaction();
        $this->applyFromDto($transaction, $input);

        $this->guardSpendableFunds($transaction);
        $this->guardValidTransfer($transaction);

        $this->em->persist($transaction);
        $this->em->flush();

        return $transaction;
    }

    public function update(Transaction $transaction, TransactionInputDto $input): void
    {
        $this->guardNotLinkedToAsset($transaction);
        $this->guardStrictlyPositive($input->amount);

        $this->applyFromDto($transaction, $input);

        // The row still holds its previous values in database: they must not weigh on the new guard.
        $this->guardSpendableFunds($transaction, excludedTransaction: $transaction);
        $this->guardValidTransfer($transaction);

        $this->em->flush();
    }

    public function delete(Transaction $transaction): void
    {
        $this->guardNotLinkedToAsset($transaction);

        $transaction->softDelete();
        $this->em->flush();
    }

    private function applyFromDto(Transaction $transaction, TransactionInputDto $input): void
    {
        $transaction
            ->setSpace($input->space)
            ->setAccount($input->account)
            ->setDestinationAccount($this->resolveDestinationAccount($input))
            ->setType($input->type)
            ->setAmount($input->amount)
            ->setFxRate($this->exchangeRateService->getRate($input->account->getCurrency(), $input->space->getCurrency()))
            ->setDestinationAmount($this->resolveDestinationAmount($input))
            ->setDate($input->date)
            ->setDescription($input->description)
            ->setCategory($input->category);
    }

    /**
     * Only a transfer has a destination. The form exposes the field whatever the type is
     * selected, so a value posted with another type is discarded rather than stored.
     */
    private function resolveDestinationAccount(TransactionInputDto $input): ?Account
    {
        return $input->type === TransactionTypeEnum::TRANSFER ? $input->destinationAccount : null;
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
     * Guard that the source account has sufficient available funds for the transaction.
     * Income transactions are exempt from this check.
     */
    private function guardSpendableFunds(Transaction $transaction, ?Transaction $excludedTransaction = null): void
    {
        if ($transaction->getType() === TransactionTypeEnum::INCOME) {
            return;
        }

        $this->accountBalanceService->guardAvailableFunds(
            $transaction->getAccount(),
            $transaction->getAmount(),
            $excludedTransaction,
        );
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
