<?php

declare(strict_types=1);

namespace App\Controller\Space;

use App\Entity\Space;
use App\Entity\User;
use App\Enum\SpaceTypeEnum;
use App\Form\SpaceFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/spaces', name: 'app_space_')]
class SpaceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/switch/{id}', name: 'switch', methods: ['POST'])]
    public function switch(Request $request, Space $space): Response
    {
        if (!$this->isCsrfTokenValid('space_switch', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->denyAccessUnlessGranted('VIEW', $space);

        $request->getSession()->set('flooze_active_space_id', $space->getId());

        return $this->redirect($request->headers->get('Referer') ?: $this->generateUrl('app_home'));
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
    public function new(Request $request): Response
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
            $this->em->persist($space);
            $this->em->flush();

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
            $this->em->flush();
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

        $name = $space->getName();

        // If the deleted space is currently active, remove it from the session to avoid broken references
        if ($request->getSession()->get('flooze_active_space_id') === $space->getId()) {
            $request->getSession()->remove('flooze_active_space_id');
        }

        $this->em->remove($space);
        $this->em->flush();

        $this->addFlash('success', 'Espace "' . $name . '" supprimé.');

        return $this->redirectToRoute('app_space_index');
    }


}
