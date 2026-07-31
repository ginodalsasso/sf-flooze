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
        private readonly AssetEntryTransactionService $transactionService,
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

    /**
     * Soft-delete an entry and reverse its linked transaction balance effects.
     * The AssetEntry FK is preserved on soft-deleted transactions for audit.
     */
    public function delete(AssetEntry $entry): void
    {
        $this->transactionService->deleteForEntry($entry);
        $entry->softDelete();
        $this->em->flush();
    }

    /** Calculate realized P&L for a sell entry in space currency using FIFO. */
    public function calculateRealizedPnL(AssetEntry $sellEntry): ?string
    {
        if ($sellEntry->getKind() !== AssetEntryKindEnum::SELL) {
            return null;
        }

        $sellQty = $sellEntry->getQuantity();

        $buys = $this->entryRepository->findBuysByAsset($sellEntry->getAsset());

        $remainingQty = $sellQty;
        $matchedCost = '0';

        foreach ($buys as $buy) {
            if (bccomp($remainingQty, '0', 8) <= 0) {
                break;
            }

            $buyQty = $buy->getQuantity();
            $buyPrice = $buy->getUnitPrice();
            $buyFx = $buy->getFxRate();

            $matched = bccomp($remainingQty, $buyQty, 8) <= 0 ? $remainingQty : $buyQty;

            // cost in space currency: matched × buyPrice × buyFx
            $cost = bcmul(bcmul($matched, $buyPrice, 8), $buyFx, 6);
            $matchedCost = bcadd($matchedCost, $cost, 6);
            $remainingQty = bcsub($remainingQty, $matched, 8);
        }

        if (bccomp($remainingQty, '0', 8) > 0) {
            return null;
        }

        $sellProceeds = $sellEntry->getTotalAmountInSpaceCurrency();

        return bcsub(bcsub($sellProceeds, $matchedCost, 2), $sellEntry->getFees(), 2);
    }

    /** Calculate total realized P&L across all sells for an asset. */
    public function calculateTotalRealizedPnL(Asset $asset): string
    {
        $total = '0';
        foreach ($asset->getEntries() as $entry) {
            if ($entry->isDeleted()) {
                continue;
            }
            if ($entry->getKind() === AssetEntryKindEnum::SELL) {
                $pnl = $this->calculateRealizedPnL($entry);
                if ($pnl !== null) {
                    $total = bcadd($total, $pnl, 2);
                }
            }
        }

        return $total;
    }

    // Guard methods for validating input data
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
                    'Cannot sell %s units: only %s held.',
                    $input->quantity,
                    $heldQty,
                ));
            }
        }
    }

    // Guard that a numeric string is strictly positive, throwing an exception if not.
    private function guardStrictlyPositive(?string $value, string $fieldName): void
    {
        $isPositive = $value !== null && bccomp($value, '0', 8) > 0;
        
        if (!$isPositive) {
            throw new \InvalidArgumentException(sprintf('%s must be strictly positive.', $fieldName));
        }
    }
}
