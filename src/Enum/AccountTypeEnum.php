<?php

declare(strict_types=1);

namespace App\Enum;

enum AccountTypeEnum: string
{
    case BANK = 'bank';
    case CASH = 'cash';
    case CRYPTO = 'crypto';
    case SAVING = 'saving';
    case STOCK = 'stock';

    public function label(): string
    {
        return match($this) {
            self::BANK   => 'Compte bancaire',
            self::CASH   => 'Espèces',
            self::CRYPTO => 'Crypto-monnaies',
            self::SAVING => 'Épargne',
            self::STOCK  => 'Actions / Bourse',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::BANK   => 'landmark',
            self::CASH   => 'wallet',
            self::CRYPTO => 'coins',
            self::SAVING => 'piggy-bank',
            self::STOCK  => 'trending-up',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::BANK   => 'info',
            self::CASH   => 'positive',
            self::CRYPTO => 'fiscal',
            self::SAVING => 'alert',
            self::STOCK  => 'soon',
        };
    }
}
