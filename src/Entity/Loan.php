<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoanRepository;
use App\Trait\SpaceScopeTrait;
use App\Trait\TimestampTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A loan file: the bank, the funded property and the release date.
 *
 * Capital, rate, insurance rate and duration live on its lines, never here — a file bundling
 * a main loan, a PTZ and an employer loan has no single rate to speak of. Its totals are
 * derived by LoanAmortizationService.
 */
#[ORM\Entity(repositoryClass: LoanRepository::class)]
#[ORM\Table(name: 'loan')]
#[ORM\HasLifecycleCallbacks]
class Loan
{
    use TimestampTrait;
    use SpaceScopeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Property::class)]
    #[ORM\JoinColumn(name: 'property_id', nullable: false)]
    private Property $property;

    #[ORM\Column(name: 'bank_name', length: 100)]
    private string $bankName;

    /** Release of the funds: every line starts from it, and the first instalment falls a month later. */
    #[ORM\Column(name: 'start_date', type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    /** @var Collection<int, LoanLine> */
    #[ORM\OneToMany(targetEntity: LoanLine::class, mappedBy: 'loan', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['amount' => 'DESC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProperty(): Property
    {
        return $this->property;
    }

    public function setProperty(Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function setBankName(string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    /** @return Collection<int, LoanLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(LoanLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setLoan($this);
        }

        return $this;
    }

    public function removeLine(LoanLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }
}
