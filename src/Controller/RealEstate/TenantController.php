<?php

declare(strict_types=1);

namespace App\Controller\RealEstate;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\Tenant;
use App\Form\RealEstate\TenantFormType;
use App\Repository\Contract\TenantRepositoryInterface;
use App\Service\RealEstate\Contract\TenantServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/real-estate/tenants', name: 'app_tenant_')]
class TenantController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly TenantServiceInterface $tenantService,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        return $this->render('real_estate/tenant/index.html.twig', [
            'tenants' => $this->tenantRepository->findBySpace($space),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $tenant = new Tenant();
        $form = $this->createForm(TenantFormType::class, $tenant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tenant->setSpace($space);
            $this->tenantService->save($tenant);
            $this->addFlash('success', 'Locataire « ' . $tenant->getFullName() . ' » créé.');

            return $this->redirectToRoute('app_tenant_index');
        }

        return $this->render('real_estate/tenant/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Tenant $tenant): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $tenant->getSpace());

        $form = $this->createForm(TenantFormType::class, $tenant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tenantService->save($tenant);
            $this->addFlash('success', 'Locataire « ' . $tenant->getFullName() . ' » mis à jour.');

            return $this->redirectToRoute('app_tenant_index');
        }

        return $this->render('real_estate/tenant/edit.html.twig', [
            'form' => $form,
            'tenant' => $tenant,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Tenant $tenant): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $tenant->getSpace());

        if (!$this->isCsrfTokenValid('tenant_delete_' . $tenant->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $tenant->getFullName();

        try {
            $this->tenantService->delete($tenant);
            $this->addFlash('success', 'Locataire « ' . $name . ' » supprimé.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_tenant_index');
    }
}
