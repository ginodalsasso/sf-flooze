<?php

declare(strict_types=1);

namespace App\Enum;

enum CurrencyEnum: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case NZD = 'NZD';

    public function label(): string
    {
        return match($this) {
            self::EUR => 'Euro',
            self::USD => 'Dollar américain',
            self::GBP => 'Livre sterling',
            self::CHF => 'Franc suisse',
            self::NZD => 'Dollar néo-zélandais',
        };
    }

    public function symbol(): string
    {
        return match($this) {
            self::EUR => '€',
            self::USD => '$',
            self::GBP => '£',
            self::CHF => 'CHF',
            self::NZD => 'NZ$',
        };
    }

    public function display(): string
    {
        return sprintf('%s %s', $this->value, $this->symbol());
    }
}
