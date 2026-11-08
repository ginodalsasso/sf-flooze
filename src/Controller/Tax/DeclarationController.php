<?php

declare(strict_types=1);

namespace App\Controller\Tax;

use App\Controller\ActiveSpaceControllerTrait;
use App\Service\RealEstate\Contract\LoanChargeServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use App\Service\Tax\Contract\TaxDeclarationCsvGeneratorInterface;
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
        private readonly TaxDeclarationCsvGeneratorInterface $taxDeclarationCsvGenerator,
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

    #[Route('/{year}/export.csv', name: 'export_csv', requirements: ['year' => '\d{4}'], methods: ['GET'])]
    public function exportCsv(int $year): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        // The service falls back to another year when this one has no movements: refuse rather than export the wrong one.
        $summary = $this->taxSummaryService->summarize($space, $year);
        if ($summary->year !== $year) {
            throw $this->createNotFoundException();
        }

        $csv = $this->taxDeclarationCsvGenerator->generate(
            $summary,
            $this->loanChargeService->chargesForYear($space, $year),
        );

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="declaration-%d.csv"', $year),
        ]);
    }
}
