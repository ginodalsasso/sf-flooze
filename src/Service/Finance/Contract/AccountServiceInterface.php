<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\Account;

/** Account persistence and soft-delete. */
interface AccountServiceInterface
{
    public function save(Account $account): void;

    public function delete(Account $account): void;
}
