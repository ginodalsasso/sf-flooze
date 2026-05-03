<?php

declare(strict_types=1);

namespace App\Controller\Space;

use App\Entity\Space;
use App\Entity\User;
use App\Enum\SpaceTypeEnum;
use App\Form\SpaceFormType;
use App\Repository\SpaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/spaces', name: 'app_space_')]
class SpaceController extends AbstractController
{


    #[Route('/switch/{id}', name: 'switch', methods: ['POST'])]
    public function switch(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('space_switch', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();

        foreach ($user->getSpaces() as $space) {
            if ($space->getId() === $id) {
                $request->getSession()->set('flooze_active_space_id', $id);
                break;
            }
        }
        $referer = $request->headers->get('Referer');

        return $this->redirect($referer ?: $this->generateUrl('app_home'));
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('space/index.html.twig', [
            'spaces' => $user->getSpaces()->toArray(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $hasPersonal = false;
        $hasPro = false;

        foreach ($user->getSpaces() as $existing) {
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
            foreach ($user->getSpaces() as $existing) {
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
            $em->persist($space);
            $em->flush();

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
    public function edit(int $id, Request $request, SpaceRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $space = $this->resolveOwnedSpace($id, $user, $repo);

        $form = $this->createForm(SpaceFormType::class, $space, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Espace "' . $space->getName() . '" mis à jour.');

            return $this->redirectToRoute('app_space_index');
        }

        return $this->render('space/edit.html.twig', [
            'form' => $form,
            'space' => $space,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, SpaceRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('space_delete_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $space = $this->resolveOwnedSpace($id, $user, $repo);
        $name = $space->getName();

        // If the deleted space is currently active, remove it from the session to avoid broken references
        if ($request->getSession()->get('flooze_active_space_id') === $id) {
            $request->getSession()->remove('flooze_active_space_id');
        }

        $em->remove($space);
        $em->flush();

        $this->addFlash('success', 'Espace "' . $name . '" supprimé.');

        return $this->redirectToRoute('app_space_index');
    }

    // Ensure the space belongs to the user
    private function resolveOwnedSpace(int $id, User $user, SpaceRepository $repo): Space
    {
        $space = $repo->find($id);

        if (!$space || $space->getUser() !== $user) {
            throw $this->createNotFoundException('Espace introuvable.');
        }

        return $space;
    }
}
