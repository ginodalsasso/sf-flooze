<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\Asset;

/** Asset persistence and deletion, including its linked transactions. */
interface AssetServiceInterface
{
    public function save(Asset $asset): void;

    public function delete(Asset $asset): void;
}
