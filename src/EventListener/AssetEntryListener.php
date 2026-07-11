<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AssetEntry;
use App\Enum\AssetEntryKindEnum;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Keeps account balances in sync with asset entries.
 *
 * - Buy  -> money leaves the account (debit)
 * - Sell -> money enters the account (credit)
 * - Dividend -> money enters the account (credit)
 *
 * On removal, the effect is reversed so that deleting an entry (or an asset
 * with cascading entries) restores the account balance.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: AssetEntry::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: AssetEntry::class)]
class AssetEntryListener
{
    public function prePersist(AssetEntry $entry): void
    {
        $this->applyEntry($entry, reverse: false);
    }

    public function preRemove(AssetEntry $entry): void
    {
        $this->applyEntry($entry, reverse: true);
    }

    private function applyEntry(AssetEntry $entry, bool $reverse): void
    {
        $amount = $entry->getTotalAmountInSpaceCurrency();

        // Holding account: credited on buy, debited on sell.
        $account = $entry->getAccount();
        if ($account !== null) {
            $holdingSign = match ($entry->getKind()) {
                AssetEntryKindEnum::Buy => 1.0,
                AssetEntryKindEnum::Sell => -1.0,
                AssetEntryKindEnum::Dividend => 0.0,
            };

            if ($reverse) {
                $holdingSign = -$holdingSign;
            }

            if ($holdingSign !== 0.0) {
                $account->adjustBalance($amount * $holdingSign);
            }
        }

        // Funding account: debited on buy, credited on sell/dividend.
        $fundingAccount = $entry->getFundingAccount();
        if ($fundingAccount !== null) {
            $fundingSign = match ($entry->getKind()) {
                AssetEntryKindEnum::Buy => -1.0,
                AssetEntryKindEnum::Sell, AssetEntryKindEnum::Dividend => 1.0,
            };

            if ($reverse) {
                $fundingSign = -$fundingSign;
            }

            $fundingAccount->adjustBalance($amount * $fundingSign);
        }
    }
}
