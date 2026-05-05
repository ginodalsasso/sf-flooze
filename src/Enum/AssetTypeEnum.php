<?php

declare(strict_types=1);

namespace App\Enum;

enum AssetTypeEnum: string
{
    case Stock = 'stock';
    case Crypto = 'crypto';
    case Etf = 'etf';

    public function label(): string
    {
        return match($this) {
            self::Stock  => 'Action',
            self::Crypto => 'Crypto-monnaie',
            self::Etf    => 'ETF',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Stock  => 'trending-up',
            self::Crypto => 'coins',
            self::Etf    => 'bar-chart-2',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::Stock  => 'info',
            self::Crypto => 'fiscal',
            self::Etf    => 'positive',
        };
    }
}
