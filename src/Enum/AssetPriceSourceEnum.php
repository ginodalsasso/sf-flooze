<?php

declare(strict_types=1);

namespace App\Enum;

/** Where a displayed asset price comes from: the user must never confuse a live quote with an old one. */
enum AssetPriceSourceEnum: string
{
    case MARKET = 'market';
    case CACHED = 'cached';
    case TRADE  = 'trade';

    public function label(): string
    {
        return match($this) {
            self::MARKET => 'Cours du marché',
            self::CACHED => 'Dernier cours connu',
            self::TRADE  => 'Dernière opération',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::MARKET => 'positive',
            self::CACHED => 'alert',
            self::TRADE  => 'info',
        };
    }

    /** A live quote can be trusted enough to prefill a price field; the others must be retyped. */
    public function isLive(): bool
    {
        return $this === self::MARKET;
    }
}
