<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionTypeEnum: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match($this) {
            self::INCOME   => 'Revenu',
            self::EXPENSE  => 'Dépense',
            self::TRANSFER => 'Virement',
        };
    }

    /**
     * Label used for asset-holding accounts (crypto/stock). Only withdrawals
     * and outgoing transfers make sense for these accounts: incoming money is
     * always a transfer from another tracked account.
     */
    public function assetLabel(): string
    {
        return match($this) {
            self::EXPENSE  => 'Retrait de fonds',
            self::TRANSFER => 'Virement sortant',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::INCOME   => 'arrow-down-left',
            self::EXPENSE  => 'arrow-up-right',
            self::TRANSFER => 'arrow-left-right',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::INCOME   => 'positive',
            self::EXPENSE  => 'negative',
            self::TRANSFER => 'info',
        };
    }
}
