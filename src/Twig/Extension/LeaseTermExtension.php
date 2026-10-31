<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Dto\RealEstate\LeaseTermDto;
use App\Entity\Lease;
use App\Service\RealEstate\Contract\LeaseTermServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LeaseTermExtension extends AbstractExtension
{
    public function __construct(
        private readonly LeaseTermServiceInterface $leaseTermService,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('lease_term', [$this, 'getCurrentTerm']),
        ];
    }

    public function getCurrentTerm(Lease $lease): LeaseTermDto
    {
        return $this->leaseTermService->currentTerm($lease);
    }
}
