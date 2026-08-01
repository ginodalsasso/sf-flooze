<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Dto\Finance\AccountDetailDto;
use App\Dto\Finance\TransactionFilterDto;
use App\Entity\Account;

/** Builds the detailed view of an account: transactions and monthly summary. */
interface AccountDetailServiceInterface
{
    /** $filter narrows the movement list; the monthly summary always covers the current month. */
    public function build(Account $account, TransactionFilterDto $filter): AccountDetailDto;
}
