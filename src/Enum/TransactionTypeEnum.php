<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionTypeEnum: string
{
    case Income   = 'income';
    case Expense  = 'expense';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match($this) {
            self::Income   => 'Revenu',
            self::Expense  => 'Dépense',
            self::Transfer => 'Virement',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Income   => 'arrow-down-left',
            self::Expense  => 'arrow-up-right',
            self::Transfer => 'arrow-left-right',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::Income   => 'positive',
            self::Expense  => 'negative',
            self::Transfer => 'info',
        };
    }

    /** Sign multiplier to apply to account balance: +1 for income, -1 for expense/transfer */
    public function balanceSign(): int
    {
        return match($this) {
            self::Income   => 1,
            self::Expense  => -1,
            self::Transfer => -1,
        };
    }
}
