<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\AssetEntryInputDto;
use App\Entity\Asset;
use App\Entity\AssetEntry;
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
     * Record a buy, sell or dividend entry.
     *
     * Validation is kind-specific: buy/sell require quantity and unit price,
     * dividend requires an amount. The linked account balances are updated by
     * the Doctrine entity listener.
     *
     * @throws \InvalidArgumentException if a required amount/quantity/price is not positive
     * @throws \RuntimeException if a sell quantity exceeds the held quantity
     */
    public function recordEntry(AssetEntryInputDto $input): AssetEntry
    {
        $this->validate($input);

        $entry = new AssetEntry();
        $entry->setAsset($input->asset)
            ->setSpace($input->space)
            ->setDate($input->date)
            ->setKind($input->kind)
            ->setQuantity($input->quantity ?? $input->amount ?? '0')
            ->setUnitPrice($input->unitPrice ?? '1')
            ->setFxRate($input->fxRate)
            ->setFees($input->fees)
            ->setAccount($input->account)
            ->setFundingAccount($input->fundingAccount)
            ->setNote($input->note);

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

    private function validate(AssetEntryInputDto $input): void
    {
        switch ($input->kind) {
            case AssetEntryKindEnum::BUY:
            case AssetEntryKindEnum::SELL:
                $this->guardStrictlyPositive($input->quantity, sprintf('%s quantity', ucfirst($input->kind->value)));
                $this->guardStrictlyPositive($input->unitPrice, sprintf('%s unit price', ucfirst($input->kind->value)));
                break;
            case AssetEntryKindEnum::DIVIDEND:
                $this->guardStrictlyPositive($input->amount, 'Dividend amount');
                break;
        }

        if ($input->kind === AssetEntryKindEnum::SELL && $input->quantity !== null) {
            $heldQty = $this->entryRepository->getTotalQuantity($input->asset);
            if (bccomp($input->quantity, $heldQty, 8) > 0) {
                throw new \RuntimeException(sprintf(
                    'Cannot sell %.8f units: only %.8f held.',
                    (float) $input->quantity,
                    (float) $heldQty
                ));
            }
        }
    }

    private function guardStrictlyPositive(?string $value, string $fieldName): void
    {
        if ($value === null || (float) $value <= 0.0) {
            throw new \InvalidArgumentException(sprintf('%s must be strictly positive.', $fieldName));
        }
    }
}
