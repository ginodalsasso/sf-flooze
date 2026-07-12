<?php

declare(strict_types=1);

namespace App\Enum;

enum AssetTypeEnum: string
{
    case STOCK = 'stock';
    case CRYPTO = 'crypto';
    case ETF = 'etf';

    public function label(): string
    {
        return match($this) {
            self::STOCK  => 'Action',
            self::CRYPTO => 'Crypto-monnaie',
            self::ETF    => 'ETF',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::STOCK  => 'trending-up',
            self::CRYPTO => 'coins',
            self::ETF    => 'bar-chart-2',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::STOCK  => 'info',
            self::CRYPTO => 'fiscal',
            self::ETF    => 'positive',
        };
    }

    /** Whether this asset type pays dividends */
    public function supportsDividend(): bool
    {
        return match($this) {
            self::STOCK  => true,
            self::CRYPTO => false,
            self::ETF    => true,
        };
    }

    /** Whether this asset type has a unit price (crypto does, stocks do) */
    public function hasUnitPrice(): bool
    {
        return match($this) {
            self::STOCK  => true,
            self::CRYPTO => true,
            self::ETF    => true,
        };
    }

    /** Whether FX rate is relevant (always true, but crypto often quoted in EUR/USD) */
    public function fxRateRelevant(): bool
    {
        return true;
    }

    /** Human-readable description of what this asset type represents */
    public function description(): string
    {
        return match($this) {
            self::STOCK  => 'Action individuelle (dividendes possibles)',
            self::CRYPTO => 'Crypto-monnaie (pas de dividende, staking possible)',
            self::ETF    => 'ETF (dividendes ou capitalisation)',
        };
    }

    /** Account type that must exist to hold assets of this type */
    public function requiredAccountType(): AccountTypeEnum
    {
        return match($this) {
            self::STOCK, self::ETF => AccountTypeEnum::STOCK,
            self::CRYPTO => AccountTypeEnum::CRYPTO,
        };
    }
}
