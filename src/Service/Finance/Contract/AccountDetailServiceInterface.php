<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\AccountDetailDto;
use App\Entity\Account;

/** Builds the detailed view of an account: transactions and monthly summary. */
interface AccountDetailServiceInterface
{
    public function build(Account $account): AccountDetailDto;
}
