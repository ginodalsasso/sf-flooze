<?php

declare(strict_types=1);

namespace App\Controller\Space;

use App\Controller\SafeRedirectTrait;
use App\Entity\Space;
use App\Entity\User;
use App\Enum\SpaceTypeEnum;
use App\Form\SpaceFormType;
use App\Service\Space\Contract\SpaceServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/spaces', name: 'app_space_')]
class SpaceController extends AbstractController
{
    use SafeRedirectTrait;

    public function __construct(
        private readonly SpaceServiceInterface $spaceService,
    ) {}

    #[Route('/switch/{id}', name: 'switch', methods: ['POST'])]
    public function switch(Request $request, Space $space): Response
    {
        if (!$this->isCsrfTokenValid('space_switch', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->denyAccessUnlessGranted('VIEW', $space);

        $request->getSession()->set('flooze_active_space_id', $space->getId());

        return $this->safeRedirect($request, $request->headers->get('Referer'), 'app_home');
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('space/index.html.twig', [
            'spaces'   => $this->spaceService->listActive($user),
            'archived' => $this->spaceService->listArchived($user),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $hasPersonal = false;
        $hasPro = false;

        // Archived spaces free their type slot: restoring one is refused if it was re-used.
        foreach ($this->spaceService->listActive($user) as $existing) {
            match ($existing->getType()) {
                SpaceTypeEnum::PERSONAL     => $hasPersonal = true,
                SpaceTypeEnum::PROFESSIONAL => $hasPro = true,
            };
        }

        if ($hasPersonal && $hasPro) {
            $this->addFlash('info', 'Tu as déjà un espace personnel et un espace professionnel.');

            return $this->redirectToRoute('app_home');
        }

        $space = new Space();
        $space->setType($hasPersonal ? SpaceTypeEnum::PROFESSIONAL : SpaceTypeEnum::PERSONAL);

        $form = $this->createForm(SpaceFormType::class, $space);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($this->spaceService->listActive($user) as $existing) {
                if ($existing->getType() === $space->getType()) {
                    $this->addFlash('error', sprintf(
                        'Tu as déjà un espace %s.',
                        $space->getType() === SpaceTypeEnum::PERSONAL ? 'personnel' : 'professionnel'
                    ));

                    return $this->render('space/new.html.twig', [
                        'form' => $form,
                        'has_personal' => $hasPersonal,
                        'has_pro' => $hasPro,
                    ]);
                }
            }

            $space->setUser($user);
            $this->spaceService->save($space);

            $request->getSession()->set('flooze_active_space_id', $space->getId());
            $this->addFlash('success', 'Espace "' . $space->getName() . '" créé avec succès.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('space/new.html.twig', [
            'form' => $form,
            'has_personal' => $hasPersonal,
            'has_pro' => $hasPro,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Space $space): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $space);

        $form = $this->createForm(SpaceFormType::class, $space, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->spaceService->save($space);
            $this->addFlash('success', 'Espace "' . $space->getName() . '" mis à jour.');

            return $this->redirectToRoute('app_space_index');
        }

        return $this->render('space/edit.html.twig', [
            'form' => $form,
            'space' => $space,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Space $space): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $space);

        if (!$this->isCsrfTokenValid('space_delete_' . $space->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // The archived space must stop being the active one, or every page would resolve to it.
        if ($request->getSession()->get('flooze_active_space_id') === $space->getId()) {
            $request->getSession()->remove('flooze_active_space_id');
        }

        $this->spaceService->archive($space);
        $this->addFlash('success', 'Espace "' . $space->getName() . '" archivé.');

        return $this->redirectToRoute('app_space_index');
    }

    #[Route('/{id}/restore', name: 'restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(Request $request, Space $space): Response
    {
        $this->denyAccessUnlessGranted('RESTORE', $space);

        if (!$this->isCsrfTokenValid('space_restore_' . $space->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->spaceService->restore($space);
            $this->addFlash('success', 'Espace "' . $space->getName() . '" restauré.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_space_index');
    }
}
