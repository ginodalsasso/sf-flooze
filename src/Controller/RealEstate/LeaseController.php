<?php

declare(strict_types=1);

namespace App\Controller\RealEstate;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\Lease;
use App\Form\RealEstate\LeaseFormType;
use App\Form\RealEstate\RentPaymentConfirmFormType;
use App\Repository\Contract\LeaseRepositoryInterface;
use App\Repository\Contract\PropertyRepositoryInterface;
use App\Repository\Contract\RentPaymentRepositoryInterface;
use App\Service\RealEstate\Contract\LeaseRentScheduleServiceInterface;
use App\Service\RealEstate\Contract\LeaseServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/real-estate/leases', name: 'app_lease_')]
class LeaseController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly LeaseServiceInterface $leaseService,
        private readonly LeaseRepositoryInterface $leaseRepository,
        private readonly PropertyRepositoryInterface $propertyRepository,
        private readonly SpaceResolverInterface $spaceResolver,
        private readonly LeaseRentScheduleServiceInterface $rentScheduleService,
        private readonly RentPaymentRepositoryInterface $rentPaymentRepository,
        private readonly ClockInterface $clock,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        return $this->render('real_estate/lease/index.html.twig', [
            'leases' => $this->leaseRepository->findBySpace($space),
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

        $lease = new Lease();
        $form = $this->createForm(LeaseFormType::class, $lease, ['space' => $space]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lease->setSpace($space);

            try {
                $this->leaseService->save($lease);
                $this->addFlash('success', 'Bail créé sur « ' . $lease->getProperty()->getName() . ' ».');

                return $this->redirectToRoute('app_lease_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('real_estate/lease/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Lease $lease): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $lease->getSpace());

        return $this->render('real_estate/lease/show.html.twig', [
            'lease' => $lease,
            'nextDue' => $this->rentScheduleService->nextDue($lease, $this->clock->now()),
            'confirmForm' => $this->createForm(RentPaymentConfirmFormType::class, null, ['space' => $lease->getSpace()])->createView(),
            'payments' => $this->rentPaymentRepository->findByLease($lease),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Lease $lease): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $lease->getSpace());

        $form = $this->createForm(LeaseFormType::class, $lease, ['space' => $lease->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->leaseService->save($lease);
                $this->addFlash('success', 'Bail mis à jour.');

                return $this->redirectToRoute('app_lease_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('real_estate/lease/edit.html.twig', [
            'form' => $form,
            'lease' => $lease,
        ]);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, Lease $lease): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $lease->getSpace());

        if (!$this->isCsrfTokenValid('lease_toggle_' . $lease->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->leaseService->toggleActive($lease);
        $this->addFlash('success', $lease->isActive() ? 'Bail réactivé.' : 'Bail clôturé.');

        return $this->redirectToRoute('app_lease_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Lease $lease): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $lease->getSpace());

        if (!$this->isCsrfTokenValid('lease_delete_' . $lease->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->leaseService->delete($lease);
        $this->addFlash('success', 'Bail supprimé.');

        return $this->redirectToRoute('app_lease_index');
    }
}
