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
     * Record a buy entry. Creates the AssetEntry and links it to the asset.
     *
     * @throws \InvalidArgumentException if quantity or unitPrice is not positive
     */
    public function recordBuy(
        Asset $asset,
        Space $space,
        \DateTimeImmutable $date,
        string $quantity,
        string $unitPrice,
        string $fxRate,
        string $fees,
        ?Account $account = null,
        ?string $note = null,
    ): AssetEntry {
        $qty = (float) $quantity;
        $price = (float) $unitPrice;

        if ($qty <= 0.0) {
            throw new \InvalidArgumentException('Buy quantity must be strictly positive.');
        }
        if ($price <= 0.0) {
            throw new \InvalidArgumentException('Buy unit price must be strictly positive.');
        }

        $entry = new AssetEntry();
        $entry->setAsset($asset)
            ->setSpace($space)
            ->setDate($date)
            ->setKind(AssetEntryKindEnum::Buy)
            ->setQuantity($quantity)
            ->setUnitPrice($unitPrice)
            ->setFxRate($fxRate)
            ->setFees($fees)
            ->setAccount($account)
            ->setNote($note);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * Record a sell entry. Validates that sufficient quantity is available.
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
        ?Account $account = null,
        ?string $note = null,
    ): AssetEntry {
        $qty = (float) $quantity;
        $price = (float) $unitPrice;

        if ($qty <= 0.0) {
            throw new \InvalidArgumentException('Sell quantity must be strictly positive.');
        }
        if ($price <= 0.0) {
            throw new \InvalidArgumentException('Sell unit price must be strictly positive.');
        }

        $heldQty = $asset->getTotalQuantity();
        if ($qty > $heldQty) {
            throw new \RuntimeException(sprintf(
                'Cannot sell %.8f units: only %.8f held.',
                $qty,
                $heldQty
            ));
        }

        $entry = new AssetEntry();
        $entry->setAsset($asset)
            ->setSpace($space)
            ->setDate($date)
            ->setKind(AssetEntryKindEnum::Sell)
            ->setQuantity($quantity)
            ->setUnitPrice($unitPrice)
            ->setFxRate($fxRate)
            ->setFees($fees)
            ->setAccount($account)
            ->setNote($note);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * Record a dividend entry. Quantity here represents the dividend amount per share
     * or total dividend depending on convention. We use it as total dividend in asset currency.
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
        ?Account $account = null,
        ?string $note = null,
    ): AssetEntry {
        $divAmount = (float) $amount;

        if ($divAmount <= 0.0) {
            throw new \InvalidArgumentException('Dividend amount must be strictly positive.');
        }

        $entry = new AssetEntry();
        $entry->setAsset($asset)
            ->setSpace($space)
            ->setDate($date)
            ->setKind(AssetEntryKindEnum::Dividend)
            ->setQuantity($amount)
            ->setUnitPrice('1')
            ->setFxRate($fxRate)
            ->setFees($fees)
            ->setAccount($account)
            ->setNote($note);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /** Delete an entry and flush. */
    public function delete(AssetEntry $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
    }

    /** Calculate realized P&L for a sell entry in space currency. */
    public function calculateRealizedPnL(AssetEntry $sellEntry): ?float
    {
        if ($sellEntry->getKind() !== AssetEntryKindEnum::Sell) {
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
            if ($entry->getKind() === AssetEntryKindEnum::Sell) {
                $pnl = $this->calculateRealizedPnL($entry);
                if ($pnl !== null) {
                    $total += $pnl;
                }
            }
        }

        return $total;
    }
}
