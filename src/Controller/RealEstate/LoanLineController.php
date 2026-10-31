<?php

declare(strict_types=1);

namespace App\Controller\RealEstate;

use App\Entity\Loan;
use App\Entity\LoanLine;
use App\Form\RealEstate\LoanLineFormType;
use App\Service\RealEstate\Contract\LoanLineServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LoanLineController extends AbstractController
{
    public function __construct(
        private readonly LoanLineServiceInterface $loanLineService,
    ) {}

    #[Route(
        '/real-estate/loans/{loan}/lines/new',
        name: 'app_loan_line_new',
        requirements: ['loan' => '\d+'],
    )]
    public function new(Request $request, Loan $loan): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $loan->getSpace());

        $line = new LoanLine();
        $form = $this->createForm(LoanLineFormType::class, $line);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $line->setLoan($loan)->setSpace($loan->getSpace());

            try {
                $this->loanLineService->save($line);
                $this->addFlash('success', 'Ligne « ' . $line->getLabel() . ' » ajoutée.');

                return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('real_estate/loan/line_new.html.twig', [
            'form' => $form,
            'loan' => $loan,
        ]);
    }

    #[Route(
        '/real-estate/loan-lines/{id}/edit',
        name: 'app_loan_line_edit',
        requirements: ['id' => '\d+'],
    )]
    public function edit(Request $request, LoanLine $line): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $line->getSpace());

        $form = $this->createForm(LoanLineFormType::class, $line);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->loanLineService->save($line);
                $this->addFlash('success', 'Ligne mise à jour. L\'échéancier a été recalculé.');

                return $this->redirectToRoute('app_loan_show', ['id' => $line->getLoan()->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('real_estate/loan/line_edit.html.twig', [
            'form' => $form,
            'line' => $line,
        ]);
    }

    #[Route(
        '/real-estate/loan-lines/{id}/delete',
        name: 'app_loan_line_delete',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    public function delete(Request $request, LoanLine $line): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $line->getSpace());

        if (!$this->isCsrfTokenValid('loan_line_delete_' . $line->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $loanId = $line->getLoan()->getId();
        $label = $line->getLabel();

        $this->loanLineService->delete($line);
        $this->addFlash('success', 'Ligne « ' . $label . ' » supprimée.');

        return $this->redirectToRoute('app_loan_show', ['id' => $loanId]);
    }
}
