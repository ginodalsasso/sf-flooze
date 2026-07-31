<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AssetEntry;
use App\Service\Finance\Contract\AssetEntryServiceInterface;
use App\Service\Finance\Contract\AssetEntryTransactionServiceInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Keeps Transaction records in sync with asset entries.
 *
 * Asset entries are the source of truth for asset-related cash movements.
 * Manual edit/delete of the generated transactions is blocked in the UI.
 *
 * Delete is handled directly in AssetEntryService (not here) to support
 * soft-delete: em->remove() is never called, so preRemove would not fire.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: AssetEntry::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: AssetEntry::class)]
class AssetEntryListener
{
    public function __construct(
        private readonly AssetEntryTransactionServiceInterface $transactionService,
    ) {}

    public function prePersist(AssetEntry $entry): void
    {
        $this->transactionService->createForEntry($entry);
    }

    public function preUpdate(AssetEntry $entry): void
    {
        $this->transactionService->updateForEntry($entry);
    }
}
