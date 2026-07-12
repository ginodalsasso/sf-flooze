<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Account;
use App\Entity\Asset;
use App\Entity\AssetEntry;
use App\Entity\Space;
use App\Enum\AssetEntryKindEnum;
use App\Repository\AssetEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

class AssetEntryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetEntryRepository $entryRepository,
    ) {}

    /**
     * Record a buy entry. Creates the AssetEntry, links it to the asset and
     * decreases the linked account balance by the entry value
     * (quantity × unit price × FX rate). An account is mandatory.
     *
     * @throws \InvalidArgumentException if quantity, unitPrice is not positive or account is missing
     */
    public function recordBuy(
        Asset $asset,
        Space $space,
        \DateTimeImmutable $date,
        string $quantity,
        string $unitPrice,
        string $fxRate,
        string $fees,
        Account $account,
        Account $fundingAccount,
        ?string $note = null,
    ): AssetEntry {
        $this->guardStrictlyPositive($quantity, 'Buy quantity');
        $this->guardStrictlyPositive($unitPrice, 'Buy unit price');

        $entry = new AssetEntry();
        $entry->setAsset($asset)
            ->setSpace($space)
            ->setDate($date)
            ->setKind(AssetEntryKindEnum::BUY)
            ->setQuantity($quantity)
            ->setUnitPrice($unitPrice)
            ->setFxRate($fxRate)
            ->setFees($fees)
            ->setAccount($account)
            ->setFundingAccount($fundingAccount)
            ->setNote($note);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * Record a sell entry. Validates that sufficient quantity is available and
     * increases the account balance by the entry value
     * (quantity × unit price × FX rate).
     *
     * @throws \InvalidArgumentException if quantity or unitPrice is not positive
     * @throws \RuntimeException if sell quantity exceeds held quantity
     */
    public function recordSell(
        Asset $asset,
        Space $space,
        \DateTimeImmutable $date,
        string $quantity,
        string $unitPrice,
        string $fxRate,
        string $fees,
        Account $account,
        Account $fundingAccount,
        ?string $note = null,
    ): AssetEntry {
        $this->guardStrictlyPositive($quantity, 'Sell quantity');
        $this->guardStrictlyPositive($unitPrice, 'Sell unit price');

        $heldQty = $this->entryRepository->getTotalQuantity($asset);
        if (bccomp($quantity, $heldQty, 8) > 0) {
            throw new \RuntimeException(sprintf(
                'Cannot sell %.8f units: only %.8f held.',
                (float) $quantity,
                (float) $heldQty
            ));
        }

        $entry = new AssetEntry();
        $entry->setAsset($asset)
            ->setSpace($space)
            ->setDate($date)
            ->setKind(AssetEntryKindEnum::SELL)
            ->setQuantity($quantity)
            ->setUnitPrice($unitPrice)
            ->setFxRate($fxRate)
            ->setFees($fees)
            ->setAccount($account)
            ->setFundingAccount($fundingAccount)
            ->setNote($note);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * Record a dividend entry. Quantity here represents the dividend amount per share
     * or total dividend depending on convention. We use it as total dividend in asset currency.
     * When an account is provided, the net dividend is added to the account balance.
     *
     * @throws \InvalidArgumentException if dividend amount is not positive
     */
    public function recordDividend(
        Asset $asset,
        Space $space,
        \DateTimeImmutable $date,
        string $amount,
        string $fxRate,
        string $fees,
        Account $account,
        Account $fundingAccount,
        ?string $note = null,
    ): AssetEntry {
        $this->guardStrictlyPositive($amount, 'Dividend amount');

        $entry = new AssetEntry();
        $entry->setAsset($asset)
            ->setSpace($space)
            ->setDate($date)
            ->setKind(AssetEntryKindEnum::DIVIDEND)
            ->setQuantity($amount)
            ->setUnitPrice('1')
            ->setFxRate($fxRate)
            ->setFees($fees)
            ->setAccount($account)
            ->setFundingAccount($fundingAccount)
            ->setNote($note);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /** Delete an entry. The linked account balance is restored by the entity listener. */
    public function delete(AssetEntry $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
    }

    /** Calculate realized P&L for a sell entry in space currency. */
    public function calculateRealizedPnL(AssetEntry $sellEntry): ?float
    {
        if ($sellEntry->getKind() !== AssetEntryKindEnum::SELL) {
            return null;
        }

        $sellQty = (float) $sellEntry->getQuantity();
        $sellPrice = (float) $sellEntry->getUnitPrice();
        $sellFx = (float) $sellEntry->getFxRate();

        // Get buy entries in FIFO order
        $buys = $this->entryRepository->findBuysByAsset($sellEntry->getAsset());

        $remainingQty = $sellQty;
        $matchedCost = 0.0;

        foreach ($buys as $buy) {
            if ($remainingQty <= 0.0) {
                break;
            }

            $buyQty = (float) $buy->getQuantity();
            $buyPrice = (float) $buy->getUnitPrice();
            $buyFx = (float) $buy->getFxRate();

            $matched = min($remainingQty, $buyQty);
            $matchedCost += $matched * $buyPrice * $buyFx;
            $remainingQty -= $matched;
        }

        if ($remainingQty > 0.0) {
            // Should not happen if validation is correct, but defensive
            return null;
        }

        $sellProceeds = $sellQty * $sellPrice * $sellFx;

        return $sellProceeds - $matchedCost - (float) $sellEntry->getFees();
    }

    /** Calculate total realized P&L across all sells for an asset. */
    public function calculateTotalRealizedPnL(Asset $asset): float
    {
        $total = 0.0;
        foreach ($asset->getEntries() as $entry) {
            if ($entry->getKind() === AssetEntryKindEnum::SELL) {
                $pnl = $this->calculateRealizedPnL($entry);
                if ($pnl !== null) {
                    $total += $pnl;
                }
            }
        }

        return $total;
    }

    private function guardStrictlyPositive(string $value, string $fieldName): void
    {
        if ((float) $value <= 0.0) {
            throw new \InvalidArgumentException(sprintf('%s must be strictly positive.', $fieldName));
        }
    }
}
