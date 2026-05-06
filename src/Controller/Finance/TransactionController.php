<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionTypeEnum;
use App\Form\Finance\TransactionFormType;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\Service\Finance\TransactionService;
use App\Service\Space\SpaceResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/transactions', name: 'app_transaction_')]
class TransactionController extends AbstractController
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly TransactionRepository $transactionRepository,
        private readonly AccountRepository $accountRepository,
        private readonly SpaceResolver $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $space = $this->spaceResolver->resolve($user);

        if ($space === null) {
            return $this->redirectToRoute('app_space_new');
        }

        $this->denyAccessUnlessGranted('VIEW', $space);

        $typeFilter    = $request->query->get('type');
        $accountFilter = $request->query->getInt('account');

        $type    = $typeFilter    ? TransactionTypeEnum::tryFrom($typeFilter) : null;
        $account = $accountFilter ? $this->accountRepository->find($accountFilter) : null;

        // Ensure the filtered account belongs to the current space
        if ($account !== null && $account->getSpace() !== $space) {
            $account = null;
        }

        return $this->render('finance/transaction/index.html.twig', [
            'transactions'  => $this->transactionRepository->findBySpace($space, $type, $account),
            'accounts'      => $this->accountRepository->findBySpace($space),
            'typeFilter'    => $type,
            'accountFilter' => $account,
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

        $transaction = new Transaction();
        $transaction->setDate(new \DateTimeImmutable());

        $form = $this->createForm(TransactionFormType::class, $transaction, ['space' => $space]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $transaction->setSpace($space);
            $this->transactionService->save($transaction);
            $this->addFlash('success', 'Transaction enregistrée.');

            return $this->redirectToRoute('app_transaction_index');
        }

        return $this->render('finance/transaction/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Transaction $transaction): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $transaction->getSpace());

        // Snapshot before form binding
        $oldAccount     = $transaction->getAccount();
        $oldType        = $transaction->getType();
        $oldAmount      = $transaction->getAmount();
        $oldDestAccount = $transaction->getDestinationAccount();

        $form = $this->createForm(TransactionFormType::class, $transaction, ['space' => $transaction->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->transactionService->update($transaction, $oldAccount, $oldType, $oldAmount, $oldDestAccount);
            $this->addFlash('success', 'Transaction mise à jour.');

            return $this->redirectToRoute('app_transaction_index');
        }

        return $this->render('finance/transaction/edit.html.twig', [
            'form'        => $form,
            'transaction' => $transaction,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Transaction $transaction): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $transaction->getSpace());

        if (!$this->isCsrfTokenValid('transaction_delete_' . $transaction->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->transactionService->delete($transaction);
        $this->addFlash('success', 'Transaction supprimée.');

        return $this->redirectToRoute('app_transaction_index');
    }
}
