<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Account;
use App\Entity\AssetEntry;
use App\Entity\Transaction;
use App\Enum\AssetEntryKindEnum;
use App\Enum\TransactionTypeEnum;
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
 * listeners, where flushing inside the listener would break the unit of work.
 */
final readonly class AssetEntryTransactionService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function createForEntry(AssetEntry $entry): void
    {
        $this->createHoldingTransaction($entry);
        $this->createFundingTransaction($entry);
    }

    /**
     * Synchronises linked transactions when the AssetEntry is edited.
     *
     * This is not just a timestamp update: amount, date, type, account or fees
     * may have changed, so the linked Transaction rows must reflect the entry.
     */
    public function updateForEntry(AssetEntry $entry): void
    {
        $expected = $this->buildExpectedTransactions($entry);
        $existing = $entry->getTransactions()->toArray();

        // Update existing transactions that still target the same account.
        foreach ($expected as $expectedTx) {
            [$match, $key] = $this->findTransactionByAccount($existing, $expectedTx['account']);

            if ($match !== null) {
                unset($existing[$key]);
                $this->updateTransaction($match, $entry, $expectedTx);
            } else {
                $this->createTransaction($entry, $expectedTx);
            }
        }

        // Any remaining existing transaction no longer matches the entry and is removed.
        foreach ($existing as $orphan) {
            $entry->removeTransaction($orphan);
            $this->reverseBalanceEffect($orphan);
            $orphan->softDelete();
        }
    }

    public function deleteForEntry(AssetEntry $entry): void
    {
        foreach ($entry->getTransactions()->toArray() as $transaction) {
            $entry->removeTransaction($transaction);
            $this->reverseBalanceEffect($transaction);
            $transaction->softDelete();
        }
    }

    /**
     * @return array<int, array{account: Account, type: TransactionTypeEnum, amount: float}>
     */
    private function buildExpectedTransactions(AssetEntry $entry): array
    {
        $expected = [];

        $account = $entry->getAccount();
        if ($account !== null) {
            $type = match ($entry->getKind()) {
                AssetEntryKindEnum::Buy => TransactionTypeEnum::Income,
                AssetEntryKindEnum::Sell => TransactionTypeEnum::Expense,
                AssetEntryKindEnum::Dividend => null,
            };

            if ($type !== null) {
                $expected[] = [
                    'account' => $account,
                    'type' => $type,
                    'amount' => $entry->getTotalAmountInSpaceCurrency(),
                ];
            }
        }

        $fundingAccount = $entry->getFundingAccount();
        if ($fundingAccount !== null) {
            $type = match ($entry->getKind()) {
                AssetEntryKindEnum::Buy => TransactionTypeEnum::Expense,
                AssetEntryKindEnum::Sell, AssetEntryKindEnum::Dividend => TransactionTypeEnum::Income,
            };

            $expected[] = [
                'account' => $fundingAccount,
                'type' => $type,
                'amount' => $this->calculateFundingAmount($entry),
            ];
        }

        return $expected;
    }

    /**
     * @param Transaction[] $transactions
     * @return array{0: Transaction|null, 1: int|string|null}
     */
    private function findTransactionByAccount(array $transactions, Account $account): array
    {
        foreach ($transactions as $key => $transaction) {
            if ($transaction->getAccount() === $account) {
                return [$transaction, $key];
            }
        }

        return [null, null];
    }

    /**
     * @param array{account: Account, type: TransactionTypeEnum, amount: float} $expected
     */
    private function createTransaction(AssetEntry $entry, array $expected): void
    {
        $transaction = new Transaction();
        $transaction
            ->setSpace($entry->getSpace())
            ->setAccount($expected['account'])
            ->setType($expected['type'])
            ->setAmount($this->formatAmount($expected['amount']))
            ->setDate($entry->getDate())
            ->setDescription($this->buildDescription($entry))
            ->setAssetEntry($entry);

        $entry->addTransaction($transaction);
        $this->em->persist($transaction);
        $this->applyBalance($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());
    }

    /**
     * @param array{account: Account, type: TransactionTypeEnum, amount: float} $expected
     */
    private function updateTransaction(Transaction $transaction, AssetEntry $entry, array $expected): void
    {
        $oldAccount = $transaction->getAccount();
        $oldType = $transaction->getType();
        $oldAmount = $transaction->getAmount();

        $transaction
            ->setAccount($expected['account'])
            ->setType($expected['type'])
            ->setAmount($this->formatAmount($expected['amount']))
            ->setDate($entry->getDate())
            ->setDescription($this->buildDescription($entry));

        $this->applyBalance($oldAccount, $oldType, $this->negate($oldAmount));
        $this->applyBalance($transaction->getAccount(), $transaction->getType(), $transaction->getAmount());
    }

    private function createHoldingTransaction(AssetEntry $entry): void
    {
        $account = $entry->getAccount();
        if ($account === null) {
            return;
        }

        $type = match ($entry->getKind()) {
            AssetEntryKindEnum::Buy => TransactionTypeEnum::Income,
            AssetEntryKindEnum::Sell => TransactionTypeEnum::Expense,
            AssetEntryKindEnum::Dividend => null,
        };

        if ($type === null) {
            return;
        }

        $this->createTransaction($entry, [
            'account' => $account,
            'type' => $type,
            'amount' => $entry->getTotalAmountInSpaceCurrency(),
        ]);
    }

    private function createFundingTransaction(AssetEntry $entry): void
    {
        $fundingAccount = $entry->getFundingAccount();
        if ($fundingAccount === null) {
            return;
        }

        $type = match ($entry->getKind()) {
            AssetEntryKindEnum::Buy => TransactionTypeEnum::Expense,
            AssetEntryKindEnum::Sell, AssetEntryKindEnum::Dividend => TransactionTypeEnum::Income,
        };

        $this->createTransaction($entry, [
            'account' => $fundingAccount,
            'type' => $type,
            'amount' => $this->calculateFundingAmount($entry),
        ]);
    }

    private function calculateFundingAmount(AssetEntry $entry): float
    {
        $gross = $entry->getTotalAmountInSpaceCurrency();
        $fees = (float) $entry->getFees();

        return match ($entry->getKind()) {
            AssetEntryKindEnum::Buy => $gross + $fees,
            AssetEntryKindEnum::Sell, AssetEntryKindEnum::Dividend => max(0.0, $gross - $fees),
        };
    }

    private function buildDescription(AssetEntry $entry): string
    {
        $kindLabel = match ($entry->getKind()) {
            AssetEntryKindEnum::Buy => 'Achat',
            AssetEntryKindEnum::Sell => 'Vente',
            AssetEntryKindEnum::Dividend => 'Dividende',
        };

        return sprintf('%s %s', $kindLabel, $entry->getAsset()->getTicker());
    }

    private function formatAmount(float $amount): string
    {
        return (string) round($amount, 2);
    }

    private function reverseBalanceEffect(Transaction $transaction): void
    {
        $this->applyBalance(
            $transaction->getAccount(),
            $transaction->getType(),
            $this->negate($transaction->getAmount())
        );
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
}
