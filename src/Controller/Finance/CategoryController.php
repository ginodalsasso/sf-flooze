<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\Category;
use App\Form\Finance\CategoryFormType;
use App\Repository\Contract\CategoryRepositoryInterface;
use App\Service\Finance\Contract\CategoryServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/categories', name: 'app_category_')]
class CategoryController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly CategoryServiceInterface $categoryService,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        return $this->render('finance/category/index.html.twig', [
            'roots' => $this->categoryRepository->findRootsBySpace($space),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category, ['space' => $space]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSpace($space);
            $this->categoryService->save($category);
            $this->addFlash('success', 'Catégorie "' . $category->getName() . '" créée.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('finance/category/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Category $category): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $category->getSpace());

        $form = $this->createForm(CategoryFormType::class, $category, ['space' => $category->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categoryService->save($category);
            $this->addFlash('success', 'Catégorie "' . $category->getName() . '" mise à jour.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('finance/category/edit.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Category $category): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $category->getSpace());

        if (!$this->isCsrfTokenValid('category_delete_' . $category->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $category->getName();
        $this->categoryService->delete($category);
        $this->addFlash('success', 'Catégorie "' . $name . '" supprimée.');

        return $this->redirectToRoute('app_category_index');
    }
}
