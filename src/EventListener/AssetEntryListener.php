<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AssetEntry;
use App\Service\Finance\AssetEntryTransactionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Keeps Transaction records in sync with asset entries.
 *
 * Asset entries are the source of truth for asset-related cash movements.
 * Manual edit/delete of the generated transactions is blocked in the UI.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: AssetEntry::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: AssetEntry::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: AssetEntry::class)]
class AssetEntryListener
{
    public function __construct(
        private readonly AssetEntryTransactionService $transactionService,
    ) {}

    public function prePersist(AssetEntry $entry): void
    {
        $this->transactionService->createForEntry($entry);
    }

    public function preUpdate(AssetEntry $entry): void
    {
        $this->transactionService->updateForEntry($entry);
    }

    public function preRemove(AssetEntry $entry): void
    {
        $this->transactionService->deleteForEntry($entry);
    }
}
