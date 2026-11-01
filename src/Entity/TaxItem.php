<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use App\Repository\TaxItemRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * A bare checkbox: one bucket × role line of TaxSummaryDto marked as reported on the
 * declaration. Never stores an amount — TaxSummaryService stays the only source for those.
 */
#[ORM\Entity(repositoryClass: TaxItemRepository::class)]
#[ORM\Table(name: 'tax_item')]
#[ORM\UniqueConstraint(name: 'uniq_tax_item_year_bucket_role', columns: ['tax_year_id', 'fiscal_bucket', 'fiscal_role'])]
#[ORM\HasLifecycleCallbacks]
class TaxItem
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TaxYear::class)]
    #[ORM\JoinColumn(name: 'tax_year_id', nullable: false, onDelete: 'CASCADE')]
    private TaxYear $taxYear;

    #[ORM\Column(name: 'fiscal_bucket', enumType: FiscalBucketEnum::class)]
    private FiscalBucketEnum $fiscalBucket;

    #[ORM\Column(name: 'fiscal_role', enumType: FiscalRoleEnum::class)]
    private FiscalRoleEnum $fiscalRole;

    #[ORM\Column(type: 'boolean')]
    private bool $done = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxYear(): TaxYear
    {
        return $this->taxYear;
    }

    public function setTaxYear(TaxYear $taxYear): static
    {
        $this->taxYear = $taxYear;

        return $this;
    }

    public function getFiscalBucket(): FiscalBucketEnum
    {
        return $this->fiscalBucket;
    }

    public function setFiscalBucket(FiscalBucketEnum $fiscalBucket): static
    {
        $this->fiscalBucket = $fiscalBucket;

        return $this;
    }

    public function getFiscalRole(): FiscalRoleEnum
    {
        return $this->fiscalRole;
    }

    public function setFiscalRole(FiscalRoleEnum $fiscalRole): static
    {
        $this->fiscalRole = $fiscalRole;

        return $this;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): static
    {
        $this->done = $done;

        return $this;
    }
}
