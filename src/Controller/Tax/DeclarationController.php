<?php

declare(strict_types=1);

namespace App\Controller\Tax;

use App\Controller\ActiveSpaceControllerTrait;
use App\Service\RealEstate\Contract\LoanChargeServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use App\Service\Tax\Contract\TaxSummaryServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tax/declaration', name: 'app_declaration_')]
class DeclarationController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    /**
     * Loan charges are fetched separately on purpose: the Tax domain reads the ledger and
     * nothing else, so composing the two reads belongs here, not in TaxSummaryService.
     */
    public function __construct(
        private readonly TaxSummaryServiceInterface $taxSummaryService,
        private readonly LoanChargeServiceInterface $loanChargeService,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        // 0 on a missing or non-numeric param; the service only accepts a year it offers.
        $year = $request->query->getInt('year') ?: null;
        $summary = $this->taxSummaryService->summarize($space, $year);

        return $this->render('tax/declaration/index.html.twig', [
            'summary' => $summary,
            'loanCharges' => $this->loanChargeService->chargesForYear($space, $summary->year),
        ]);
    }
}
