<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\AssetEntryInputDto;
use App\Entity\Asset;
use App\Entity\AssetEntry;
use App\Enum\AssetEntryKindEnum;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use App\Service\Finance\Contract\AssetEntryServiceInterface;
use App\Service\Finance\Contract\AssetEntryTransactionServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class AssetEntryService implements AssetEntryServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetEntryRepositoryInterface $entryRepository,
        private readonly AssetEntryTransactionServiceInterface $transactionService,
    ) {}

    /** The linked transactions are created by the Doctrine entity listener, not here. */
    public function recordEntry(AssetEntryInputDto $input): AssetEntry
    {
        $this->validate($input);

        [$quantity, $unitPrice] = $this->encodeAmount($input);

        $entry = new AssetEntry();
        $entry->setAsset($input->asset)
            ->setSpace($input->space)
            ->setDate($input->date)
            ->setKind($input->kind)
            ->setQuantity($quantity)
            ->setUnitPrice($unitPrice)
            ->setFxRate($input->fxRate)
            ->setFees($input->fees)
            ->setAccount($input->account)
            ->setFundingAccount($input->fundingAccount)
            ->setNote($input->note);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /** The AssetEntry FK is preserved on soft-deleted transactions for audit. */
    public function delete(AssetEntry $entry): void
    {
        $this->transactionService->deleteForEntry($entry);
        $entry->softDelete();
        $this->em->flush();
    }

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

    /**
     * Encode the quantity and unit price for storage, based on the kind of asset entry.
     * @return array{string, string}
     */
    private function encodeAmount(AssetEntryInputDto $input): array
    {
        [$quantity, $unitPrice] = match ($input->kind) {
            AssetEntryKindEnum::BUY,
            AssetEntryKindEnum::SELL     => [$input->quantity, $input->unitPrice],
            AssetEntryKindEnum::DIVIDEND => [$input->amount, '1'],
        };

        return [$quantity, $unitPrice];
    }

    private function validate(AssetEntryInputDto $input): void
    {
        match ($input->kind) {
            AssetEntryKindEnum::BUY,
            AssetEntryKindEnum::SELL     => $this->guardTrade($input),
            AssetEntryKindEnum::DIVIDEND => $this->guardStrictlyPositive($input->amount, 'Dividend amount'),
        };

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

    private function guardTrade(AssetEntryInputDto $input): void
    {
        $kindLabel = ucfirst($input->kind->value);

        $this->guardStrictlyPositive($input->quantity, sprintf('%s quantity', $kindLabel));
        $this->guardStrictlyPositive($input->unitPrice, sprintf('%s unit price', $kindLabel));
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
