<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Enum\CurrencyEnum;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Renders a monetary amount with its currency.
 *
 * Amounts now come from accounts holding different currencies, so the currency
 * can no longer be implicit next to a number in a template.
 */
final class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'format']),
        ];
    }

    public function format(string|float|null $amount, CurrencyEnum $currency): string
    {
        return sprintf('%s %s', number_format((float) $amount, 2, ',', ' '), $currency->value);
    }
}
