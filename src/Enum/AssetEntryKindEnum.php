<?php

declare(strict_types=1);

namespace App\Enum;

enum AssetEntryKindEnum: string
{
    case Buy      = 'buy';
    case Sell     = 'sell';
    case Dividend = 'dividend';

    public function label(): string
    {
        return match($this) {
            self::Buy      => 'Achat',
            self::Sell     => 'Vente',
            self::Dividend => 'Dividende',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Buy      => 'arrow-down-left',
            self::Sell     => 'arrow-up-right',
            self::Dividend => 'banknote',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::Buy      => 'positive',
            self::Sell     => 'negative',
            self::Dividend => 'fiscal',
        };
    }

    /** Whether this entry increases the cash position (sell or dividend) */
    public function isCashInflow(): bool
    {
        return match($this) {
            self::Buy      => false,
            self::Sell     => true,
            self::Dividend => true,
        };
    }

    /** Whether this entry affects the held quantity */
    public function affectsQuantity(): bool
    {
        return match($this) {
            self::Buy      => true,
            self::Sell     => true,
            self::Dividend => false,
        };
    }

    /** Sign multiplier for quantity: +1 for buy, -1 for sell, 0 for dividend */
    public function quantitySign(): int
    {
        return match($this) {
            self::Buy      => 1,
            self::Sell     => -1,
            self::Dividend => 0,
        };
    }
}
