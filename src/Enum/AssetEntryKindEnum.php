<?php

declare(strict_types=1);

namespace App\Enum;

enum AssetEntryKindEnum: string
{
    case BUY = 'buy';
    case SELL = 'sell';
    case DIVIDEND = 'dividend';

    public function label(): string
    {
        return match($this) {
            self::BUY      => 'Achat',
            self::SELL     => 'Vente',
            self::DIVIDEND => 'Dividende',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::BUY      => 'arrow-down-left',
            self::SELL     => 'arrow-up-right',
            self::DIVIDEND => 'banknote',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::BUY      => 'positive',
            self::SELL     => 'negative',
            self::DIVIDEND => 'fiscal',
        };
    }

    /** Whether this entry increases the cash position (sell or dividend) */
    public function isCashInflow(): bool
    {
        return match($this) {
            self::BUY      => false,
            self::SELL     => true,
            self::DIVIDEND => true,
        };
    }

    /** Whether this entry affects the held quantity */
    public function affectsQuantity(): bool
    {
        return match($this) {
            self::BUY      => true,
            self::SELL     => true,
            self::DIVIDEND => false,
        };
    }

    /** Sign multiplier for quantity: +1 for buy, -1 for sell, 0 for dividend */
    public function quantitySign(): int
    {
        return match($this) {
            self::BUY      => 1,
            self::SELL     => -1,
            self::DIVIDEND => 0,
        };
    }
}
