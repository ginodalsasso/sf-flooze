<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Entity\Account;
use App\Entity\Asset;
use App\Entity\Space;
use App\Enum\AssetEntryKindEnum;

/**
 * Input DTO for recording a buy, sell or dividend asset entry.
 *
 * The three operations share the same cash-movement context (asset, space,
 * accounts, date, FX rate, fees, note) but differ on the quantity/price side.
 * Static factories enforce which fields are required for each kind.
 */
final readonly class AssetEntryInputDto
{
    private function __construct(
        public Asset $asset,
        public Space $space,
        public \DateTimeImmutable $date,
        public string $fxRate,
        public string $fees,
        public Account $account,
        public Account $fundingAccount,
        public ?string $note,
        public AssetEntryKindEnum $kind,
        public ?string $quantity,
        public ?string $unitPrice,
        public ?string $amount,
    ) {}

    public static function buy(
        Asset $asset,
        Space $space,
        \DateTimeImmutable $date,
        string $quantity,
        string $unitPrice,
        string $fxRate,
        string $fees,
        Account $account,
        Account $fundingAccount,
        ?string $note = null,
    ): self {
        return new self(
            asset: $asset,
            space: $space,
            date: $date,
            fxRate: $fxRate,
            fees: $fees,
            account: $account,
            fundingAccount: $fundingAccount,
            note: $note,
            kind: AssetEntryKindEnum::BUY,
            quantity: $quantity,
            unitPrice: $unitPrice,
            amount: null,
        );
    }

    public static function sell(
        Asset $asset,
        Space $space,
        \DateTimeImmutable $date,
        string $quantity,
        string $unitPrice,
        string $fxRate,
        string $fees,
        Account $account,
        Account $fundingAccount,
        ?string $note = null,
    ): self {
        return new self(
            asset: $asset,
            space: $space,
            date: $date,
            fxRate: $fxRate,
            fees: $fees,
            account: $account,
            fundingAccount: $fundingAccount,
            note: $note,
            kind: AssetEntryKindEnum::SELL,
            quantity: $quantity,
            unitPrice: $unitPrice,
            amount: null,
        );
    }

    public static function dividend(
        Asset $asset,
        Space $space,
        \DateTimeImmutable $date,
        string $amount,
        string $fxRate,
        string $fees,
        Account $account,
        Account $fundingAccount,
        ?string $note = null,
    ): self {
        return new self(
            asset: $asset,
            space: $space,
            date: $date,
            fxRate: $fxRate,
            fees: $fees,
            account: $account,
            fundingAccount: $fundingAccount,
            note: $note,
            kind: AssetEntryKindEnum::DIVIDEND,
            quantity: null,
            unitPrice: null,
            amount: $amount,
        );
    }
}
