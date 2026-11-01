<?php

declare(strict_types=1);

namespace App\Controller\Tax;

use App\Controller\ActiveSpaceControllerTrait;
use App\Enum\FiscalBucketEnum;
use App\Enum\FiscalRoleEnum;
use App\Enum\TaxYearStatusEnum;
use App\Repository\Contract\TaxItemRepositoryInterface;
use App\Repository\Contract\TaxYearRepositoryInterface;
use App\Service\RealEstate\Contract\LoanChargeServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use App\Service\Tax\Contract\TaxItemServiceInterface;
use App\Service\Tax\Contract\TaxSummaryServiceInterface;
use App\Service\Tax\Contract\TaxYearServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tax/years', name: 'app_tax_year_')]
class TaxYearController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly TaxYearServiceInterface $taxYearService,
        private readonly TaxItemServiceInterface $taxItemService,
        private readonly TaxYearRepositoryInterface $taxYearRepository,
        private readonly TaxItemRepositoryInterface $taxItemRepository,
        private readonly TaxSummaryServiceInterface $taxSummaryService,
        private readonly LoanChargeServiceInterface $loanChargeService,
        private readonly SpaceResolverInterface $spaceResolver,
        private readonly ClockInterface $clock,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        return $this->render('tax/years/index.html.twig', [
            'taxYears' => $this->taxYearRepository->findBySpace($space),
            'currentYear' => (int) $this->clock->now()->format('Y'),
        ]);
    }

    #[Route('/{year}', name: 'show', requirements: ['year' => '\d{4}'])]
    public function show(int $year): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $taxYear = $this->taxYearService->getOrCreate($space, $year);
        $summary = $this->taxSummaryService->summarize($space, $year);

        $itemsByKey = [];
        foreach ($this->taxItemRepository->findByTaxYear($taxYear) as $item) {
            $itemsByKey[$item->getFiscalBucket()->value . '-' . $item->getFiscalRole()->value] = $item->isDone();
        }

        return $this->render('tax/years/show.html.twig', [
            'taxYear' => $taxYear,
            'summary' => $summary,
            'loanCharges' => $this->loanChargeService->chargesForYear($space, $summary->year),
            'itemsByKey' => $itemsByKey,
            'regimeChoices' => $taxYear->getRegimeChoices() ?? [],
        ]);
    }

    #[Route('/{year}/status', name: 'status', methods: ['POST'], requirements: ['year' => '\d{4}'])]
    public function status(Request $request, int $year): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $taxYear = $this->taxYearService->getOrCreate($space, $year);

        if (!$this->isCsrfTokenValid('tax_year_status_' . $taxYear->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $to = TaxYearStatusEnum::from((string) $request->request->get('to'));
            $this->taxYearService->transition($taxYear, $to);
            $this->addFlash('success', 'Statut mis à jour.');
        } catch (\ValueError) {
            $this->addFlash('error', 'Statut invalide.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_tax_year_show', ['year' => $year]);
    }

    #[Route('/{year}/regime', name: 'regime', methods: ['POST'], requirements: ['year' => '\d{4}'])]
    public function regime(Request $request, int $year): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $taxYear = $this->taxYearService->getOrCreate($space, $year);

        if (!$this->isCsrfTokenValid('tax_year_regime_' . $taxYear->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $regime = $request->request->get('regime') ?: null;

        try {
            $bucket = FiscalBucketEnum::from((string) $request->request->get('bucket'));
            $this->taxYearService->setRegimeChoice($taxYear, $bucket, $regime);
            $this->addFlash('success', 'Régime enregistré.');
        } catch (\ValueError) {
            $this->addFlash('error', 'Catégorie fiscale invalide.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_tax_year_show', ['year' => $year]);
    }

    #[Route('/{year}/note', name: 'note', methods: ['POST'], requirements: ['year' => '\d{4}'])]
    public function note(Request $request, int $year): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $taxYear = $this->taxYearService->getOrCreate($space, $year);

        if (!$this->isCsrfTokenValid('tax_year_note_' . $taxYear->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->taxYearService->setNote($taxYear, $request->request->get('note'));
        $this->addFlash('success', 'Note enregistrée.');

        return $this->redirectToRoute('app_tax_year_show', ['year' => $year]);
    }

    #[Route('/{year}/items/toggle', name: 'item_toggle', methods: ['POST'], requirements: ['year' => '\d{4}'])]
    public function itemToggle(Request $request, int $year): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $taxYear = $this->taxYearService->getOrCreate($space, $year);

        if (!$this->isCsrfTokenValid('tax_year_item_toggle_' . $taxYear->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $bucket = FiscalBucketEnum::from((string) $request->request->get('bucket'));
            $role = FiscalRoleEnum::from((string) $request->request->get('role'));
            $this->taxItemService->toggle($taxYear, $bucket, $role);
        } catch (\ValueError) {
            $this->addFlash('error', 'Ligne invalide.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_tax_year_show', ['year' => $year]);
    }
}
