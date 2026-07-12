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

    /** Sign multiplier to apply to account balance: +1 for income, -1 for expense/transfer */
    public function balanceSign(): int
    {
        return match($this) {
            self::INCOME   => 1,
            self::EXPENSE  => -1,
            self::TRANSFER => -1,
        };
    }
}
