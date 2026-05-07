<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Entity\Account;
use App\Entity\User;
use App\Form\Finance\AccountFormType;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\Service\Finance\AccountService;
use App\Service\Space\SpaceResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/accounts', name: 'app_account_')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountRepository $accountRepository,
        private readonly TransactionRepository $transactionRepository,
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

        return $this->render('finance/account/index.html.twig', [
            'accounts' => $this->accountRepository->findBySpace($space),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Account $account): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $account->getSpace());

        return $this->render('finance/account/show.html.twig', [
            'account'      => $account,
            'transactions' => $this->transactionRepository->findBySpace($account->getSpace(), null, $account),
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

        $account = new Account();
        $form = $this->createForm(AccountFormType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $account->setSpace($space);
            $this->accountService->save($account);
            $this->addFlash('success', 'Compte "' . $account->getName() . '" créé.');

            return $this->redirectToRoute('app_account_index');
        }

        return $this->render('finance/account/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Account $account): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $account->getSpace());

        $form = $this->createForm(AccountFormType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->accountService->save($account);
            $this->addFlash('success', 'Compte "' . $account->getName() . '" mis à jour.');

            return $this->redirectToRoute('app_account_index');
        }

        return $this->render('finance/account/edit.html.twig', [
            'form' => $form,
            'account' => $account,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Account $account): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $account->getSpace());

        if (!$this->isCsrfTokenValid('account_delete_' . $account->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = $account->getName();
        $this->accountService->delete($account);
        $this->addFlash('success', 'Compte "' . $name . '" supprimé.');

        return $this->redirectToRoute('app_account_index');
    }
}
