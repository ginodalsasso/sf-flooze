<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Enum\AssetEntryKindEnum;

/**
 * Search criteria for the operations of one asset.
 *
 * Deliberately narrower than the transaction filter: an asset holds a handful of
 * operations, so kind and period are enough to find one.
 */
class AssetEntryFilterDto
{
    public ?AssetEntryKindEnum $kind = null;
    public ?\DateTimeImmutable $dateFrom = null;
    public ?\DateTimeImmutable $dateTo = null;

    public function isEmpty(): bool
    {
        return $this->kind === null && $this->dateFrom === null && $this->dateTo === null;
    }
}
