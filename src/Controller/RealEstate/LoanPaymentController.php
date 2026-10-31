<?php

declare(strict_types=1);

namespace App\Controller\RealEstate;

use App\Entity\Loan;
use App\Form\RealEstate\LoanPaymentConfirmFormType;
use App\Service\RealEstate\Contract\LoanPaymentServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/real-estate/loans/{loan}/payments', name: 'app_loan_payment_', requirements: ['loan' => '\d+'])]
class LoanPaymentController extends AbstractController
{
    public function __construct(
        private readonly LoanPaymentServiceInterface $loanPaymentService,
    ) {}

    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request, Loan $loan): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $loan->getSpace());

        $form = $this->createForm(LoanPaymentConfirmFormType::class, null, ['space' => $loan->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->loanPaymentService->confirm(
                    $loan,
                    $form->get('account')->getData(),
                    $form->get('category')->getData(),
                );
                $this->addFlash('success', 'Échéance de prêt réglée.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Formulaire invalide.');
        }

        return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()]);
    }
}
