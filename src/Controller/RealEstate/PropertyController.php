<?php

declare(strict_types=1);

namespace App\Controller\RealEstate;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\Property;
use App\Form\RealEstate\PropertyFormType;
use App\Repository\Contract\LeaseRepositoryInterface;
use App\Repository\Contract\LoanRepositoryInterface;
use App\Repository\Contract\PropertyRepositoryInterface;
use App\Service\RealEstate\Contract\PropertyServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/real-estate/properties', name: 'app_property_')]
class PropertyController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly PropertyServiceInterface $propertyService,
        private readonly PropertyRepositoryInterface $propertyRepository,
        private readonly LeaseRepositoryInterface $leaseRepository,
        private readonly LoanRepositoryInterface $loanRepository,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        return $this->render('real_estate/property/index.html.twig', [
            'properties' => $this->propertyRepository->findBySpace($space),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $property = new Property();
        $form = $this->createForm(PropertyFormType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setSpace($space);
            $this->propertyService->save($property);
            $this->addFlash('success', 'Bien « ' . $property->getName() . ' » créé.');

            return $this->redirectToRoute('app_property_index');
        }

        return $this->render('real_estate/property/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Property $property): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $property->getSpace());

        return $this->render('real_estate/property/show.html.twig', [
            'property' => $property,
            'leases'   => $this->leaseRepository->findByProperty($property),
            'loans'    => $this->loanRepository->findByProperty($property),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Property $property): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $property->getSpace());

        $form = $this->createForm(PropertyFormType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->propertyService->save($property);
            $this->addFlash('success', 'Bien « ' . $property->getName() . ' » mis à jour.');

            return $this->redirectToRoute('app_property_index');
        }

        return $this->render('real_estate/property/edit.html.twig', [
            'form' => $form,
            'property' => $property,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Property $property): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $property->getSpace());

        if (!$this->isCsrfTokenValid('property_delete_' . $property->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $property->getName();

        try {
            $this->propertyService->delete($property);
            $this->addFlash('success', 'Bien « ' . $name . ' » supprimé.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_property_index');
    }
}
