<?php

declare(strict_types=1);

namespace App\Enum;

enum PropertyTypeEnum: string
{
    case PRIMARY = 'primary';
    case RENTAL = 'rental';
    case SECONDARY = 'secondary';

    public function label(): string
    {
        return match($this) {
            self::PRIMARY   => 'Résidence principale',
            self::RENTAL    => 'Investissement locatif',
            self::SECONDARY => 'Résidence secondaire',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::PRIMARY   => 'house',
            self::RENTAL    => 'building-2',
            self::SECONDARY => 'palmtree',
        };
    }

    public function badgeVariant(): string
    {
        return match($this) {
            self::PRIMARY   => 'info',
            self::RENTAL    => 'positive',
            self::SECONDARY => 'alert',
        };
    }

    /** Only a rental property can carry a lease. */
    public function isRentable(): bool
    {
        return $this !== self::PRIMARY;
    }
}
