<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Entity\Asset;
use App\Entity\User;
use App\Form\Finance\AssetFormType;
use App\Repository\AssetRepository;
use App\Service\Finance\AssetService;
use App\Service\Space\SpaceResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/assets', name: 'app_asset_')]
class AssetController extends AbstractController
{
    public function __construct(
        private readonly AssetService $assetService,
        private readonly AssetRepository $assetRepository,
        private readonly SpaceResolver $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $space = $this->spaceResolver->resolve($user);

        if ($space === null) {
            return $this->redirectToRoute('app_space_new');
        }

        $this->denyAccessUnlessGranted('VIEW', $space);

        return $this->render('finance/asset/index.html.twig', [
            'assets' => $this->assetRepository->findBySpace($space),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $space = $this->spaceResolver->resolve($user);

        if ($space === null) {
            return $this->redirectToRoute('app_space_new');
        }

        $this->denyAccessUnlessGranted('EDIT', $space);

        $asset = new Asset();
        $form = $this->createForm(AssetFormType::class, $asset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset->setSpace($space);
            $this->assetService->save($asset);
            $this->addFlash('success', 'Actif "' . $asset->getTicker() . '" ajouté.');

            return $this->redirectToRoute('app_asset_index');
        }

        return $this->render('finance/asset/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Asset $asset): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $asset->getSpace());

        $form = $this->createForm(AssetFormType::class, $asset);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assetService->save($asset);
            $this->addFlash('success', 'Actif "' . $asset->getTicker() . '" mis à jour.');

            return $this->redirectToRoute('app_asset_index');
        }

        return $this->render('finance/asset/edit.html.twig', [
            'form'  => $form,
            'asset' => $asset,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Asset $asset): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $asset->getSpace());

        if (!$this->isCsrfTokenValid('asset_delete_' . $asset->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $ticker = $asset->getTicker();
        $this->assetService->delete($asset);
        $this->addFlash('success', 'Actif "' . $ticker . '" supprimé.');

        return $this->redirectToRoute('app_asset_index');
    }
}
