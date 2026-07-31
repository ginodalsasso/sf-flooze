<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Asset;
use App\Service\Finance\Contract\AssetEntryTransactionServiceInterface;
use App\Service\Finance\Contract\AssetServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class AssetService implements AssetServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetEntryTransactionServiceInterface $entryTransactionService,
    ) {}

    public function save(Asset $asset): void
    {
        $this->em->persist($asset);
        $this->em->flush();
    }

    public function delete(Asset $asset): void
    {
        foreach ($asset->getEntries() as $entry) {
            if ($entry->isDeleted()) {
                continue;
            }
            $this->entryTransactionService->deleteForEntry($entry);
        }

        $this->em->remove($asset);
        $this->em->flush();
    }
}
