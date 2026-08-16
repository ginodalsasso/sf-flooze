<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\ExpectedTransactionDto;
use App\Entity\Account;
use App\Entity\AssetEntry;
use App\Entity\Transaction;
use App\Enum\AssetEntryKindEnum;
use App\Enum\TransactionTypeEnum;
use App\Service\Finance\Contract\AssetEntryTransactionServiceInterface;
use App\Service\Finance\Contract\ExchangeRateServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps Transaction records in sync with AssetEntry operations.
 *
 * Each AssetEntry creates independent transactions on the linked accounts:
 *  - holding account (account): credited on buy, debited on sell
 *  - funding account (fundingAccount): debited on buy, credited on sell/dividend
 *
 * Manual edit/delete of transactions linked to an AssetEntry is blocked in the UI
 * and in TransactionService; the AssetEntry remains the source of truth.
 *
 * This service intentionally does NOT flush: it is called from Doctrine entity
 * listeners (prePersist/preUpdate), where flushing inside the listener would break
 * the unit of work. For delete, it IS called from the service (not a listener) and
 * the caller is responsible for flushing.
 */
final readonly class AssetEntryTransactionService implements AssetEntryTransactionServiceInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExchangeRateServiceInterface $exchangeRateService,
    ) {}

    public function createForEntry(AssetEntry $entry): void
    {
        foreach ($this->buildExpectedTransactions($entry) as $expected) {
            $this->createTransaction($entry, $expected);
        }
    }

    /** Amount, date, type, account or fees may have changed: the linked rows must reflect the entry. */
    public function updateForEntry(AssetEntry $entry): void
    {
        $expected = $this->buildExpectedTransactions($entry);
        $existing = $entry->getTransactions()->toArray();

        // Update existing transactions that still target the same account.
        foreach ($expected as $expectedTx) {
            [$match, $key] = $this->findTransaction($existing, $expectedTx);

            if ($match !== null) {
                unset($existing[$key]);
                $this->updateTransaction($match, $entry, $expectedTx);
            } else {
                $this->createTransaction($entry, $expectedTx);
            }
        }

        // Remaining transactions no longer match the entry: soft-delete them.
        foreach ($existing as $orphan) {
            $entry->removeTransaction($orphan);
            $orphan->softDelete();
        }
    }

    /** The AssetEntry FK is preserved on the transaction for audit purposes. */
    public function deleteForEntry(AssetEntry $entry): void
    {
        foreach ($entry->getTransactions()->toArray() as $transaction) {
            $transaction->softDelete();
        }
    }

    /**
     * @return ExpectedTransactionDto[]
     */
    private function buildExpectedTransactions(AssetEntry $entry): array
    {
        $expectedTransactions = [];

        $account = $entry->getAccount();
        if ($account !== null) {
            $type = match ($entry->getKind()) {
                AssetEntryKindEnum::BUY => TransactionTypeEnum::INCOME,
                AssetEntryKindEnum::SELL => TransactionTypeEnum::EXPENSE,
                AssetEntryKindEnum::DIVIDEND => null,
            };

            if ($type !== null) {
                $expectedTransactions[] = new ExpectedTransactionDto(
                    account: $account,
                    type: $type,
                    amount: $entry->getTotalAmountInSpaceCurrency(),
                );
            }
        }

        $fundingAccount = $entry->getFundingAccount();
        if ($fundingAccount !== null) {
            $type = match ($entry->getKind()) {
                AssetEntryKindEnum::BUY => TransactionTypeEnum::EXPENSE,
                AssetEntryKindEnum::SELL, AssetEntryKindEnum::DIVIDEND => TransactionTypeEnum::INCOME,
            };

            $expectedTransactions[] = new ExpectedTransactionDto(
                account: $fundingAccount,
                type: $type,
                amount: $this->calculateFundingAmount($entry),
            );
        }

        return $expectedTransactions;
    }

    /**
     * Both legs land on the same account when the buy is internal: the type tells them apart.
     *
     * @param Transaction[] $transactions
     * @return array{0: Transaction|null, 1: int|string|null}
     */
    private function findTransaction(array $transactions, ExpectedTransactionDto $expected): array
    {
        foreach ($transactions as $key => $transaction) {
            if ($transaction->getAccount() === $expected->account && $transaction->getType() === $expected->type) {
                return [$transaction, $key];
            }
        }

        $noneFound = [null, null];

        return $noneFound;
    }

    private function createTransaction(AssetEntry $entry, ExpectedTransactionDto $expected): void
    {
        $transaction = new Transaction();
        $transaction
            ->setSpace($entry->getSpace())
            ->setAccount($expected->account)
            ->setType($expected->type)
            ->setAmount($this->toAccountCurrency($expected->amount, $expected->account, $entry->getDate()))
            ->setFxRate($this->fxRateOf($expected->account, $entry->getDate()))
            ->setDate($entry->getDate())
            ->setDescription($this->buildDescription($entry))
            ->setAssetEntry($entry);

        $entry->addTransaction($transaction);
        $this->em->persist($transaction);
    }

    private function updateTransaction(Transaction $transaction, AssetEntry $entry, ExpectedTransactionDto $expected): void
    {
        $transaction
            ->setAccount($expected->account)
            ->setType($expected->type)
            ->setAmount($this->toAccountCurrency($expected->amount, $expected->account, $entry->getDate()))
            ->setFxRate($this->fxRateOf($expected->account, $entry->getDate()))
            ->setDate($entry->getDate())
            ->setDescription($this->buildDescription($entry));
    }

    /**
     * AssetEntry amounts are expressed in the space currency, account balances in the
     * account currency: the amount must be converted before it can move a balance.
     */
    private function toAccountCurrency(string $amount, Account $account, \DateTimeImmutable $date): string
    {
        $convertedAmount = $this->exchangeRateService->convert(
            $amount,
            $account->getSpace()->getCurrency(),
            $account->getCurrency(),
            $date,
        );

        return $convertedAmount;
    }

    private function fxRateOf(Account $account, \DateTimeImmutable $date): string
    {
        return $this->exchangeRateService->getRate($account->getCurrency(), $account->getSpace()->getCurrency(), $date);
    }

    /**
     * Calculates the funding amount for an AssetEntry based on its kind and fees.
     * For BUY: funding amount = total + fees
     */
    private function calculateFundingAmount(AssetEntry $entry): string
    {
        $grossAmount = $entry->getTotalAmountInSpaceCurrency();
        $feesAmount = $entry->getFees();

        $buyAmount = bcadd($grossAmount, $feesAmount, 2);
        $netAmount = bcsub($grossAmount, $feesAmount, 2);
        $isNetPositive = bccomp($netAmount, '0', 2) >= 0;

        $fundingAmount = match ($entry->getKind()) {
            AssetEntryKindEnum::BUY => $buyAmount,
            AssetEntryKindEnum::SELL,
            AssetEntryKindEnum::DIVIDEND => $isNetPositive
                ? $netAmount
                : '0.00',
        };

        return $fundingAmount;
    }

    /**
     * Build a description for an asset entry based on its kind.
     */
    private function buildDescription(AssetEntry $entry): string
    {
        $kindLabel = match ($entry->getKind()) {
            AssetEntryKindEnum::BUY => 'Achat',
            AssetEntryKindEnum::SELL => 'Vente',
            AssetEntryKindEnum::DIVIDEND => 'Dividende',
        };

        $description = sprintf('%s %s', $kindLabel, $entry->getAsset()->getTicker());

        return $description;
    }
}
