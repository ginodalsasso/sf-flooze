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

    /** Whether this asset type pays dividends */
    public function supportsDividend(): bool
    {
        return match($this) {
            self::Stock  => true,
            self::Crypto => false,
            self::Etf    => true,
        };
    }

    /** Whether this asset type has a unit price (crypto does, stocks do) */
    public function hasUnitPrice(): bool
    {
        return match($this) {
            self::Stock  => true,
            self::Crypto => true,
            self::Etf    => true,
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
            self::Stock  => 'Action individuelle (dividendes possibles)',
            self::Crypto => 'Crypto-monnaie (pas de dividende, staking possible)',
            self::Etf    => 'ETF (dividendes ou capitalisation)',
        };
    }

    /** Account type that must exist to hold assets of this type */
    public function requiredAccountType(): AccountTypeEnum
    {
        return match($this) {
            self::Stock, self::Etf => AccountTypeEnum::Stock,
            self::Crypto => AccountTypeEnum::Crypto,
        };
    }
}
