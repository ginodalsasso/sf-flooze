<?php

declare(strict_types=1);

namespace App\Controller\RealEstate;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\Loan;
use App\Form\RealEstate\LoanFormType;
use App\Form\RealEstate\LoanPaymentConfirmFormType;
use App\Repository\Contract\LoanPaymentRepositoryInterface;
use App\Repository\Contract\LoanRepositoryInterface;
use App\Repository\Contract\PropertyRepositoryInterface;
use App\Service\RealEstate\Contract\LoanAmortizationServiceInterface;
use App\Service\RealEstate\Contract\LoanPaymentServiceInterface;
use App\Service\RealEstate\Contract\LoanServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/real-estate/loans', name: 'app_loan_')]
class LoanController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly LoanServiceInterface $loanService,
        private readonly LoanAmortizationServiceInterface $amortizationService,
        private readonly LoanPaymentServiceInterface $loanPaymentService,
        private readonly LoanRepositoryInterface $loanRepository,
        private readonly LoanPaymentRepositoryInterface $loanPaymentRepository,
        private readonly PropertyRepositoryInterface $propertyRepository,
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

        return $this->render('real_estate/loan/index.html.twig', [
            'loans' => $this->loanRepository->findBySpace($space),
            'hasProperties' => $this->propertyRepository->countBySpace($space) > 0,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $loan = new Loan();
        $form = $this->createForm(LoanFormType::class, $loan, ['space' => $space]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $loan->setSpace($space);

            try {
                $this->loanService->save($loan);
                $this->addFlash('success', 'Emprunt « ' . $loan->getBankName() . ' » créé.');

                return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('real_estate/loan/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Loan $loan): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $loan->getSpace());

        return $this->render('real_estate/loan/show.html.twig', [
            'loan' => $loan,
            'schedule' => $this->amortizationService->schedule($loan),
            'currentYear' => (int) $this->clock->now()->format('Y'),
            'nextRow' => $this->loanPaymentService->nextDueRow($loan),
            'confirmForm' => $this->createForm(LoanPaymentConfirmFormType::class, null, ['space' => $loan->getSpace()])->createView(),
            'payments' => $this->loanPaymentRepository->findByLoan($loan),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Loan $loan): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $loan->getSpace());

        $form = $this->createForm(LoanFormType::class, $loan, ['space' => $loan->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->loanService->save($loan);
                $this->addFlash('success', 'Emprunt mis à jour. L\'échéancier a été recalculé.');

                return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('real_estate/loan/edit.html.twig', [
            'form' => $form,
            'loan' => $loan,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Loan $loan): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $loan->getSpace());

        if (!$this->isCsrfTokenValid('loan_delete_' . $loan->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $loan->getBankName();
        $this->loanService->delete($loan);
        $this->addFlash('success', 'Emprunt « ' . $name . ' » supprimé.');

        return $this->redirectToRoute('app_loan_index');
    }
}
