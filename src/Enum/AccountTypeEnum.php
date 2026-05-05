<?php

declare(strict_types=1);

namespace App\Enum;

enum AccountTypeEnum: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Crypto = 'crypto';
    case Saving = 'saving';
    case Stock = 'stock';

    public function label(): string
    {
        return match($this) {
            self::Bank   => 'Compte bancaire',
            self::Cash   => 'Espèces',
            self::Crypto => 'Crypto-monnaies',
            self::Saving => 'Épargne',
            self::Stock  => 'Actions / Bourse',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::Bank   => 'landmark',
            self::Cash   => 'wallet',
            self::Crypto => 'coins',
            self::Saving => 'piggy-bank',
            self::Stock  => 'trending-up',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::Bank   => 'info',
            self::Cash   => 'positive',
            self::Crypto => 'fiscal',
            self::Saving => 'alert',
            self::Stock  => 'soon',
        };
    }
}
