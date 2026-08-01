<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Controller\ActiveSpaceControllerTrait;
use App\Dto\Finance\TransactionFilterDto;
use App\Dto\Finance\TransactionInputDto;
use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use App\Form\Finance\AssetTransactionFormType;
use App\Form\Finance\ClassicTransactionFormType;
use App\Form\Finance\TransactionFilterFormType;
use App\Repository\Contract\TransactionRepositoryInterface;
use App\Service\Finance\Contract\AccountBalanceServiceInterface;
use App\Service\Finance\Contract\TransactionServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/transactions', name: 'app_transaction_')]
class TransactionController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly TransactionServiceInterface $transactionService,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly AccountBalanceServiceInterface $accountBalanceService,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        // The form only offers entities of the space, so a forged id leaves the criterion unset.
        $filter = new TransactionFilterDto();
        $filterForm = $this->createForm(TransactionFilterFormType::class, $filter, ['space' => $space]);
        $filterForm->handleRequest($request);

        // Submitted by the segmented control rather than by a form field: an unknown value is simply ignored.
        $filter->type = TransactionTypeEnum::tryFrom($request->query->getString('type'));

        return $this->render('finance/transaction/index.html.twig', [
            'transactions'     => $this->transactionRepository->findByFilter($space, $filter),
            'totals'           => $this->transactionRepository->sumByFilter($space, $filter),
            'filterForm'       => $filterForm,
            'filter'           => $filter,
            'transactionTypes' => TransactionTypeEnum::cases(),
            'space'            => $space,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $mode = $request->query->get('mode');

        if ($mode !== 'classic' && $mode !== 'asset') {
            return $this->render('finance/transaction/mode.html.twig');
        }

        $input = new TransactionInputDto();
        $input->space = $space;
        $input->date = new \DateTimeImmutable();

        $formType = $mode === 'asset'
            ? AssetTransactionFormType::class
            : ClassicTransactionFormType::class;

        $form = $this->createForm($formType, $input, ['space' => $space]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->transactionService->save($input);
                $this->addFlash('success', 'Transaction enregistrée.');

                return $this->redirectToRoute('app_transaction_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('finance/transaction/new.html.twig', [
            'form' => $form,
            'mode' => $mode,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Transaction $transaction): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $transaction->getSpace());

        // Asset-linked transactions are managed from the asset page.
        if ($transaction->isLinkedToAsset()) {
            $asset = $transaction->getAssetEntry()->getAsset();
            $this->addFlash('error', sprintf(
                'Cette transaction est liée à l\'actif %s. Modifiez l\'opération depuis la fiche de l\'actif.',
                $asset->getTicker()
            ));

            return $this->redirectToRoute('app_asset_show', ['id' => $asset->getId()]);
        }

        // Prevent editing transactions linked to a soft-deleted account
        if ($transaction->getAccount()->isDeleted()) {
            throw $this->createAccessDeniedException('Cannot modify a transaction linked to a deleted account.');
        }

        $input = TransactionInputDto::fromTransaction($transaction, $transaction->getSpace());

        $formType = $this->resolveFormType($transaction->getAccount());
        $form = $this->createForm($formType, $input, ['space' => $transaction->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->transactionService->update($transaction, $input);
                $this->addFlash('success', 'Transaction mise à jour.');
                $redirectTo = $request->query->get('redirect_to');

                return $this->safeRedirect($redirectTo, 'app_transaction_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('finance/transaction/edit.html.twig', [
            'form'        => $form,
            'transaction' => $transaction,
            'redirect_to' => $request->query->get('redirect_to'),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Transaction $transaction): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $transaction->getSpace());

        // Asset-linked transactions are managed from the asset page.
        if ($transaction->isLinkedToAsset()) {
            $asset = $transaction->getAssetEntry()->getAsset();
            $this->addFlash('error', sprintf(
                'Cette transaction est liée à l\'actif %s. Supprimez l\'opération depuis la fiche de l\'actif.',
                $asset->getTicker()
            ));

            return $this->redirectToRoute('app_asset_show', ['id' => $asset->getId()]);
        }

        if (!$this->isCsrfTokenValid('transaction_delete_' . $transaction->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Prevent deletion of transactions linked to a soft-deleted account
        if ($transaction->getAccount()->isDeleted()) {
            throw $this->createAccessDeniedException('Cannot modify a transaction linked to a deleted account.');
        }

        $this->transactionService->delete($transaction);
        $this->addFlash('success', 'Transaction supprimée.');

        return $this->safeRedirect($request->request->get('redirect_to'), 'app_transaction_index');
    }

    private function resolveFormType(Account $account): string
    {
        return $this->accountBalanceService->isAssetHoldingAccount($account)
            ? AssetTransactionFormType::class
            : ClassicTransactionFormType::class;
    }

    /** Redirect to $path if it is an internal path (starts with /), otherwise fall back to a named route. */
    private function safeRedirect(?string $path, string $fallbackRoute): \Symfony\Component\HttpFoundation\Response
    {
        if ($path !== null && str_starts_with($path, '/')) {
            return $this->redirect($path);
        }

        return $this->redirectToRoute($fallbackRoute);
    }
}
