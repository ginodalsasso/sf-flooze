<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\Tag;
use App\Form\Finance\TagFormType;
use App\Repository\Contract\TagRepositoryInterface;
use App\Repository\Contract\TransactionRepositoryInterface;
use App\Service\Finance\Contract\TagServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/tags', name: 'app_tag_')]
class TagController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly TagServiceInterface $tagService,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        return $this->render('finance/tag/index.html.twig', [
            'tags'   => $this->tagRepository->findBySpace($space),
            'usage'  => $this->transactionRepository->countByTag($space),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        // Set before validation: the uniqueness of the name is checked within the space.
        $tag = (new Tag())->setSpace($space);
        $form = $this->createForm(TagFormType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tagService->save($tag);
            $this->addFlash('success', 'Tag "' . $tag->getName() . '" créé.');

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('finance/tag/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Tag $tag): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $tag->getSpace());

        $form = $this->createForm(TagFormType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tagService->save($tag);
            $this->addFlash('success', 'Tag "' . $tag->getName() . '" mis à jour.');

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('finance/tag/edit.html.twig', [
            'form' => $form,
            'tag'  => $tag,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Tag $tag): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $tag->getSpace());

        if (!$this->isCsrfTokenValid('tag_delete_' . $tag->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $tag->getName();
        $this->tagService->delete($tag);
        $this->addFlash('success', 'Tag "' . $name . '" supprimé.');

        return $this->redirectToRoute('app_tag_index');
    }
}
