<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Entity\Account;
use App\Enum\TransactionTypeEnum;

// One Transaction an AssetEntry should produce, before it is created or synced.
final readonly class ExpectedTransactionDto
{
    public function __construct(
        public Account $account,
        public TransactionTypeEnum $type,
        public string $amount,
    ) {}
}
