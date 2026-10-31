<?php

declare(strict_types=1);

namespace App\Controller\RealEstate;

use App\Entity\Lease;
use App\Form\RealEstate\RentPaymentConfirmFormType;
use App\Service\RealEstate\Contract\RentPaymentServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/real-estate/leases/{lease}/rent-payments', name: 'app_rent_payment_')]
class RentPaymentController extends AbstractController
{
    public function __construct(
        private readonly RentPaymentServiceInterface $rentPaymentService,
    ) {}

    #[Route('/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request, Lease $lease): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $lease->getSpace());

        $form = $this->createForm(RentPaymentConfirmFormType::class, null, ['space' => $lease->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $dueDate = $this->parseDueDate($form->get('dueDate')->getData());
                $this->rentPaymentService->confirm(
                    $lease,
                    $dueDate,
                    $form->get('account')->getData(),
                    $form->get('category')->getData(),
                );
                $this->addFlash('success', 'Loyer encaissé.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Formulaire invalide.');
        }

        return $this->redirectToRoute('app_lease_show', ['id' => $lease->getId()]);
    }

    #[Route('/late', name: 'late', methods: ['POST'])]
    public function late(Request $request, Lease $lease): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $lease->getSpace());

        if (!$this->isCsrfTokenValid('rent_payment_late_' . $lease->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $dueDate = $this->parseDueDate($request->request->get('due_date'));
            $this->rentPaymentService->markLate($lease, $dueDate);
            $this->addFlash('success', 'Loyer marqué impayé.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_lease_show', ['id' => $lease->getId()]);
    }

    /** The service still checks the date belongs to the lease: this only rejects garbage. */
    private function parseDueDate(mixed $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        if ($date === false) {
            throw new \InvalidArgumentException('Date d\'échéance invalide.');
        }

        return $date;
    }
}
