<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégorie de revenus au sens fiscal français.
 *
 * C'est le cloisonnement qui rend la déclaration juste : une charge ne se déduit que des
 * revenus de sa propre catégorie. Des travaux sur un locatif s'imputent sur les revenus
 * fonciers, jamais sur un salaire.
 *
 * Les taux d'abattement ci-dessous sont les taux de droit commun. Ils changent, et chaque
 * régime micro a ses propres plafonds d'éligibilité que l'app ne vérifie pas. Ils sont
 * regroupés ici, et nulle part ailleurs, pour n'avoir qu'un seul endroit à mettre à jour.
 */
enum FiscalBucketEnum: string
{
    case REVENUS_FONCIERS = 'revenus_fonciers';
    case BIC_LMNP = 'bic_lmnp';
    case BNC = 'bnc';
    case TRAITEMENTS_SALAIRES = 'traitements_salaires';
    case RCM = 'rcm';

    public function label(): string
    {
        return match($this) {
            self::REVENUS_FONCIERS     => 'Revenus fonciers',
            self::BIC_LMNP             => 'Location meublée (BIC / LMNP)',
            self::BNC                  => 'Bénéfices non commerciaux (BNC)',
            self::TRAITEMENTS_SALAIRES => 'Traitements et salaires',
            self::RCM                  => 'Revenus de capitaux mobiliers',
        };
    }

    public function shortLabel(): string
    {
        return match($this) {
            self::REVENUS_FONCIERS     => 'Fonciers',
            self::BIC_LMNP             => 'BIC / LMNP',
            self::BNC                  => 'BNC',
            self::TRAITEMENTS_SALAIRES => 'Salaires',
            self::RCM                  => 'Capitaux mobiliers',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::REVENUS_FONCIERS     => 'building-2',
            self::BIC_LMNP             => 'bed-double',
            self::BNC                  => 'briefcase',
            self::TRAITEMENTS_SALAIRES => 'badge-euro',
            self::RCM                  => 'trending-up',
        };
    }

    /** Abattement forfaitaire du régime micro, en pourcentage. Null quand aucun ne s'applique. */
    public function microAbatementPercent(): ?string
    {
        return match($this) {
            self::REVENUS_FONCIERS     => '30',
            self::BIC_LMNP             => '50',
            self::BNC                  => '34',
            self::TRAITEMENTS_SALAIRES => '10',
            self::RCM                  => null,
        };
    }

    public function microLabel(): ?string
    {
        return match($this) {
            self::REVENUS_FONCIERS     => 'Micro-foncier',
            self::BIC_LMNP             => 'Micro-BIC',
            self::BNC                  => 'Micro-BNC',
            self::TRAITEMENTS_SALAIRES => 'Abattement forfaitaire 10 %',
            self::RCM                  => null,
        };
    }

    public function reelLabel(): string
    {
        return match($this) {
            self::REVENUS_FONCIERS                      => 'Régime réel',
            self::BIC_LMNP, self::BNC                   => 'Régime réel',
            self::TRAITEMENTS_SALAIRES                  => 'Frais réels',
            self::RCM                                   => 'Montant déclaré',
        };
    }

    /** Ce qu'il faut vérifier avant de retenir le régime micro — l'app ne le contrôle pas. */
    public function microCaveat(): ?string
    {
        return match($this) {
            self::REVENUS_FONCIERS     => 'Micro-foncier accessible sous un plafond de revenus bruts, et exclu pour certains dispositifs.',
            self::BIC_LMNP             => 'Micro-BIC soumis à un plafond de recettes ; un meublé de tourisme classé relève d\'un taux différent.',
            self::BNC                  => 'Micro-BNC soumis à un plafond de recettes.',
            self::TRAITEMENTS_SALAIRES => 'L\'abattement de 10 % est plafonné, et les frais réels ne se cumulent pas avec lui.',
            self::RCM                  => null,
        };
    }

    public function allowsMicro(): bool
    {
        return $this->microAbatementPercent() !== null;
    }

    /** Une charge ne se déduit que dans une catégorie qui admet des charges. */
    public function acceptsCharges(): bool
    {
        return $this !== self::RCM;
    }
}
