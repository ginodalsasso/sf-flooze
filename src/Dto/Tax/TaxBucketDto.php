<?php

declare(strict_types=1);

namespace App\Dto\Tax;

use App\Enum\FiscalBucketEnum;

/**
 * One catégorie de revenus, with its income, its charges, and the two possible bases.
 *
 * This is the unit the French declaration actually works on: charges are deducted here and
 * nowhere else, and under a micro regime they are replaced by a flat abattement. Nothing in
 * this object may ever be added to another bucket's figures.
 */
final readonly class TaxBucketDto
{
    public function __construct(
        public FiscalBucketEnum $bucket,
        /** @var TaxCategoryLineDto[] */
        public array $revenus,
        /** @var TaxCategoryLineDto[] */
        public array $charges,
        public string $revenusTotal,
        public string $chargesTotal,
    ) {}

    /** Assiette au réel : revenus moins charges. Négative, c'est un déficit. */
    public function reelBase(): string
    {
        return bcsub($this->revenusTotal, $this->chargesTotal, 2);
    }

    /** Abattement forfaitaire du micro. Null quand la catégorie n'en a pas. */
    public function microAbatement(): ?string
    {
        $percent = $this->bucket->microAbatementPercent();

        if ($percent === null) {
            return null;
        }

        // Half-up: an abattement truncated downwards would quietly raise the base.
        return bcadd(bcdiv(bcmul($this->revenusTotal, $percent, 6), '100', 6), '0.005', 2);
    }

    /** Assiette au micro : les charges ne sont pas déduites, l'abattement les remplace. */
    public function microBase(): ?string
    {
        $abatement = $this->microAbatement();

        return $abatement === null ? null : bcsub($this->revenusTotal, $abatement, 2);
    }

    /**
     * Le régime qui donne la plus petite assiette : 'reel', 'micro', or null when there is
     * no choice to make. Eligibility thresholds are NOT checked — see the bucket's caveat.
     */
    public function cheaperRegime(): ?string
    {
        $micro = $this->microBase();

        if ($micro === null || $this->isDeficit()) {
            return null;
        }

        $comparison = bccomp($this->reelBase(), $micro, 2);

        return $comparison === 0 ? null : ($comparison < 0 ? 'reel' : 'micro');
    }

    /** Un déficit a ses propres règles d'imputation : il ne se compare pas à un micro. */
    public function isDeficit(): bool
    {
        return bccomp($this->reelBase(), '0.00', 2) < 0;
    }

    public function isEmpty(): bool
    {
        return $this->revenus === [] && $this->charges === [];
    }
}
