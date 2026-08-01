<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\AssetEntry;

/**
 * Keeps Transaction records in sync with AssetEntry operations.
 *
 * No implementation may flush: these methods run from Doctrine entity listeners,
 * where flushing would break the unit of work. The caller flushes.
 */
interface AssetEntryTransactionServiceInterface
{
    public function createForEntry(AssetEntry $entry): void;

    /** Synchronises linked transactions when the AssetEntry is edited. */
    public function updateForEntry(AssetEntry $entry): void;

    /** Soft-delete the transactions linked to the entry. */
    public function deleteForEntry(AssetEntry $entry): void;
}
