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
 * listeners (prePersist/preUpdate), where flushing inside the listener would break
 * the unit of work. For delete, it IS called from the service (not a listener) and
 * the caller is responsible for flushing.
 */
final readonly class AssetEntryTransactionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExchangeRateService $exchangeRateService,
    ) {}

    public function createForEntry(AssetEntry $entry): void
    {
        foreach ($this->buildExpectedTransactions($entry) as $expected) {
            $this->createTransaction($entry, $expected);
        }
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

        // Remaining transactions no longer match the entry: soft-delete them.
        foreach ($existing as $orphan) {
            $entry->removeTransaction($orphan);
            $this->reverseBalanceEffect($orphan);
            $orphan->softDelete();
        }
    }

    /**
     * Soft-delete all transactions linked to this entry and reverse their balance effects.
     * The AssetEntry FK is preserved on the transaction for audit purposes.
     * The caller must flush after this call.
     */
    public function deleteForEntry(AssetEntry $entry): void
    {
        foreach ($entry->getTransactions()->toArray() as $transaction) {
            $this->reverseBalanceEffect($transaction);
            $transaction->softDelete();
        }
    }

    /**
     * @return array<int, array{account: Account, type: TransactionTypeEnum, amount: string}>
     */
    private function buildExpectedTransactions(AssetEntry $entry): array
    {
        $expected = [];

        $account = $entry->getAccount();
        if ($account !== null) {
            $type = match ($entry->getKind()) {
                AssetEntryKindEnum::BUY => TransactionTypeEnum::INCOME,
                AssetEntryKindEnum::SELL => TransactionTypeEnum::EXPENSE,
                AssetEntryKindEnum::DIVIDEND => null,
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
                AssetEntryKindEnum::BUY => TransactionTypeEnum::EXPENSE,
                AssetEntryKindEnum::SELL, AssetEntryKindEnum::DIVIDEND => TransactionTypeEnum::INCOME,
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
     * @param array{account: Account, type: TransactionTypeEnum, amount: string} $expected
     */
    private function createTransaction(AssetEntry $entry, array $expected): void
    {
        $transaction = new Transaction();
        $transaction
            ->setSpace($entry->getSpace())
            ->setAccount($expected['account'])
            ->setType($expected['type'])
            ->setAmount($this->toAccountCurrency($expected['amount'], $expected['account']))
            ->setFxRate($this->fxRateOf($expected['account']))
            ->setDate($entry->getDate())
            ->setDescription($this->buildDescription($entry))
            ->setAssetEntry($entry);

        $entry->addTransaction($transaction);
        $this->em->persist($transaction);
        $transaction->getAccount()->applyOperation($transaction->getType(), $transaction->getAmount());
    }

    /**
     * @param array{account: Account, type: TransactionTypeEnum, amount: string} $expected
     */
    private function updateTransaction(Transaction $transaction, AssetEntry $entry, array $expected): void
    {
        $oldAccount = $transaction->getAccount();
        $oldType = $transaction->getType();
        $oldAmount = $transaction->getAmount();

        $transaction
            ->setAccount($expected['account'])
            ->setType($expected['type'])
            ->setAmount($this->toAccountCurrency($expected['amount'], $expected['account']))
            ->setFxRate($this->fxRateOf($expected['account']))
            ->setDate($entry->getDate())
            ->setDescription($this->buildDescription($entry));

        $oldAccount->reverseOperation($oldType, $oldAmount);
        $transaction->getAccount()->applyOperation($transaction->getType(), $transaction->getAmount());
    }

    /**
     * AssetEntry amounts are expressed in the space currency, account balances in the
     * account currency: the amount must be converted before it can move a balance.
     */
    private function toAccountCurrency(string $amount, Account $account): string
    {
        return $this->exchangeRateService->convert(
            $amount,
            $account->getSpace()->getCurrency(),
            $account->getCurrency(),
        );
    }

    private function fxRateOf(Account $account): string
    {
        return $this->exchangeRateService->getRate($account->getCurrency(), $account->getSpace()->getCurrency());
    }

    /**
     * Calculates the funding amount for an AssetEntry based on its kind and fees.
     * For BUY: funding amount = total + fees
     */
    private function calculateFundingAmount(AssetEntry $entry): string
    {
        $gross = $entry->getTotalAmountInSpaceCurrency();
        $fees = $entry->getFees();

        return match ($entry->getKind()) {
            AssetEntryKindEnum::BUY => bcadd($gross, $fees, 2),
            AssetEntryKindEnum::SELL, AssetEntryKindEnum::DIVIDEND => (
                bccomp(bcsub($gross, $fees, 2), '0', 2) >= 0
                    ? bcsub($gross, $fees, 2)
                    : '0.00'
            ),
        };
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

        return sprintf('%s %s', $kindLabel, $entry->getAsset()->getTicker());
    }

    /**
     * Reverse the balance effect of a transaction on its account.
     */
    private function reverseBalanceEffect(Transaction $transaction): void
    {
        $transaction->getAccount()->reverseOperation($transaction->getType(), $transaction->getAmount());
    }
}
