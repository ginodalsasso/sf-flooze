<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Asset;
use Doctrine\ORM\EntityManagerInterface;

class AssetService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetEntryTransactionService $entryTransactionService,
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
