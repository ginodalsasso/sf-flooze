<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\RecurringTransaction;
use App\Form\Finance\RecurringTransactionFormType;
use App\Repository\Contract\RecurringTransactionRepositoryInterface;
use App\Service\Finance\Contract\RecurringTransactionServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/recurring', name: 'app_recurring_')]
class RecurringTransactionController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly RecurringTransactionServiceInterface $recurringService,
        private readonly RecurringTransactionRepositoryInterface $recurringRepository,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        return $this->render('finance/recurring/index.html.twig', [
            'recurrences' => $this->recurringRepository->findBySpace($space),
            'dueOccurrences' => $this->recurringService->findDueOccurrences($space),
            'space' => $space,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $recurrence = (new RecurringTransaction())->setSpace($space);
        $form = $this->createForm(RecurringTransactionFormType::class, $recurrence, ['space' => $space]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->recurringService->save($recurrence);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('finance/recurring/new.html.twig', ['form' => $form]);
            }

            $this->addFlash('success', 'Récurrence « ' . $recurrence->getLabel() . ' » créée.');

            return $this->redirectToRoute('app_recurring_index');
        }

        return $this->render('finance/recurring/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, RecurringTransaction $recurrence): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $recurrence->getSpace());

        $form = $this->createForm(RecurringTransactionFormType::class, $recurrence, [
            'space' => $recurrence->getSpace(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->recurringService->save($recurrence);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->render('finance/recurring/edit.html.twig', [
                    'form' => $form,
                    'recurrence' => $recurrence,
                ]);
            }

            $this->addFlash('success', 'Récurrence « ' . $recurrence->getLabel() . ' » mise à jour.');

            return $this->redirectToRoute('app_recurring_index');
        }

        return $this->render('finance/recurring/edit.html.twig', [
            'form' => $form,
            'recurrence' => $recurrence,
        ]);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, RecurringTransaction $recurrence): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $recurrence->getSpace());
        $this->guardCsrf($request, 'recurring_toggle_' . $recurrence->getId());

        $this->recurringService->toggleActive($recurrence);
        $this->addFlash('success', $recurrence->isActive() ? 'Récurrence reprise.' : 'Récurrence mise en pause.');

        return $this->redirectToRoute('app_recurring_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, RecurringTransaction $recurrence): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $recurrence->getSpace());
        $this->guardCsrf($request, 'recurring_delete_' . $recurrence->getId());

        $label = $recurrence->getLabel();
        $this->recurringService->delete($recurrence);
        $this->addFlash('success', 'Récurrence « ' . $label . ' » supprimée.');

        return $this->redirectToRoute('app_recurring_index');
    }

    #[Route('/{id}/confirm', name: 'confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function confirm(Request $request, RecurringTransaction $recurrence): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $recurrence->getSpace());
        $this->guardCsrf($request, 'recurring_confirm_' . $recurrence->getId());

        try {
            $this->recurringService->confirm($recurrence, $this->postedDate($request));
            $this->addFlash('success', 'Échéance « ' . $recurrence->getLabel() . ' » enregistrée.');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            // Insufficient funds land here: the occurrence stays due, the cursor did not move.
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recurring_index');
    }

    #[Route('/{id}/skip', name: 'skip', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function skip(Request $request, RecurringTransaction $recurrence): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $recurrence->getSpace());
        $this->guardCsrf($request, 'recurring_skip_' . $recurrence->getId());

        try {
            $this->recurringService->skip($recurrence, $this->postedDate($request));
            $this->addFlash('success', 'Échéance passée.');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recurring_index');
    }

    private function guardCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /** The service still checks the date belongs to the recurrence: this only rejects garbage. */
    private function postedDate(Request $request): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->request->get('date'));

        if ($date === false) {
            throw new \InvalidArgumentException('Date d\'échéance invalide.');
        }

        return $date;
    }
}
