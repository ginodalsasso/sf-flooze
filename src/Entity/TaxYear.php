<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaxYearStatusEnum;
use App\Repository\TaxYearRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * A fiscal year's review dossier: status (draft/filed/paid), the regime actually retained
 * per bucket, and a free note. Never stores an amount TaxSummaryService already computes.
 */
#[ORM\Entity(repositoryClass: TaxYearRepository::class)]
#[ORM\Table(name: 'tax_year')]
#[ORM\UniqueConstraint(name: 'uniq_tax_year_space_year', columns: ['space_id', 'year'])]
#[ORM\HasLifecycleCallbacks]
class TaxYear
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'smallint')]
    private int $year;

    #[ORM\Column(enumType: TaxYearStatusEnum::class)]
    private TaxYearStatusEnum $status;

    #[ORM\Column(name: 'regime_choices', type: 'json', nullable: true)]
    private ?array $regimeChoices = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function __construct()
    {
        $this->status = TaxYearStatusEnum::DRAFT;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getStatus(): TaxYearStatusEnum
    {
        return $this->status;
    }

    public function setStatus(TaxYearStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRegimeChoices(): ?array
    {
        return $this->regimeChoices;
    }

    public function setRegimeChoices(?array $regimeChoices): static
    {
        $this->regimeChoices = $regimeChoices;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
