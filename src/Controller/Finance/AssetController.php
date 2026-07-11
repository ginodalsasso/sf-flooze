<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Entity\Asset;
use App\Entity\User;
use App\Form\Finance\AssetDividendFormType;
use App\Form\Finance\AssetFormType;
use App\Form\Finance\AssetSellFormType;
use App\Repository\AccountRepository;
use App\Repository\AssetEntryRepository;
use App\Repository\AssetRepository;
use App\Service\Finance\AssetEntryService;
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
        private readonly AssetEntryService $assetEntryService,
        private readonly AssetRepository $assetRepository,
        private readonly AssetEntryRepository $assetEntryRepository,
        private readonly AccountRepository $accountRepository,
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

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Asset $asset): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $asset->getSpace());

        return $this->render('finance/asset/show.html.twig', [
            'asset'   => $asset,
            'entries' => $this->assetEntryRepository->findByAsset($asset),
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

        // An asset must be linked to an account. If the space has no account at
        // all, redirect to account creation before allowing any asset creation.
        if (count($this->accountRepository->findBySpace($space)) === 0) {
            $this->addFlash('error', 'Tu dois d\'abord créer un compte avant d\'ajouter un actif.');

            return $this->redirectToRoute('app_account_new');
        }

        $asset = new Asset();
        $form = $this->createForm(AssetFormType::class, $asset, ['space' => $space]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset->setSpace($space);
            $this->assetService->save($asset);

            // Create initial buy entry from the form data, linked to the selected accounts
            $this->assetEntryService->recordBuy(
                asset: $asset,
                space: $space,
                date: $form->get('entryDate')->getData(),
                quantity: (string) $form->get('entryQuantity')->getData(),
                unitPrice: (string) $form->get('entryUnitPrice')->getData(),
                fxRate: (string) $form->get('entryFxRate')->getData(),
                fees: (string) $form->get('entryFees')->getData(),
                account: $form->get('account')->getData(),
                fundingAccount: $form->get('fundingAccount')->getData(),
            );

            $this->addFlash('success', 'Actif "' . $asset->getTicker() . '" ajouté avec position d\'achat initiale.');

            return $this->redirectToRoute('app_asset_show', ['id' => $asset->getId()]);
        }

        return $this->render('finance/asset/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Asset $asset): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $asset->getSpace());

        $form = $this->createForm(AssetFormType::class, $asset, ['space' => $asset->getSpace()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assetService->save($asset);
            $this->addFlash('success', 'Actif "' . $asset->getTicker() . '" mis à jour.');

            return $this->redirectToRoute('app_asset_show', ['id' => $asset->getId()]);
        }

        return $this->render('finance/asset/edit.html.twig', [
            'form'  => $form,
            'asset' => $asset,
        ]);
    }

    #[Route('/{id}/sell', name: 'sell', requirements: ['id' => '\d+'])]
    public function sell(Request $request, Asset $asset): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $asset->getSpace());

        $space = $asset->getSpace();
        $form = $this->createForm(AssetSellFormType::class, null, ['space' => $space, 'asset' => $asset]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entry = $this->assetEntryService->recordSell(
                    asset: $asset,
                    space: $space,
                    date: $form->get('date')->getData(),
                    quantity: (string) $form->get('quantity')->getData(),
                    unitPrice: (string) $form->get('unitPrice')->getData(),
                    fxRate: (string) $form->get('fxRate')->getData(),
                    fees: (string) $form->get('fees')->getData(),
                    account: $form->get('account')->getData(),
                    fundingAccount: $form->get('fundingAccount')->getData(),
                    note: $form->get('note')->getData(),
                );

                $pnl = $this->assetEntryService->calculateRealizedPnL($entry);
                $msg = 'Vente enregistrée.';
                if ($pnl !== null) {
                    $msg .= sprintf(' Plus-value réalisée : %.2f €', $pnl);
                }

                $this->addFlash('success', $msg);

                return $this->redirectToRoute('app_asset_show', ['id' => $asset->getId()]);
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('finance/asset/sell.html.twig', [
            'form'  => $form,
            'asset' => $asset,
        ]);
    }

    #[Route('/{id}/dividend', name: 'dividend', requirements: ['id' => '\d+'])]
    public function dividend(Request $request, Asset $asset): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $asset->getSpace());

        if (!$asset->getType()->supportsDividend()) {
            $this->addFlash('error', sprintf(
                'Les %s ne distribuent pas de dividendes.',
                $asset->getType()->label()
            ));

            return $this->redirectToRoute('app_asset_show', ['id' => $asset->getId()]);
        }

        $space = $asset->getSpace();
        $form = $this->createForm(AssetDividendFormType::class, null, ['space' => $space, 'asset' => $asset]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assetEntryService->recordDividend(
                asset: $asset,
                space: $space,
                date: $form->get('date')->getData(),
                amount: (string) $form->get('amount')->getData(),
                fxRate: (string) $form->get('fxRate')->getData(),
                fees: (string) $form->get('fees')->getData(),
                account: $form->get('account')->getData(),
                fundingAccount: $form->get('fundingAccount')->getData(),
                note: $form->get('note')->getData(),
            );

            $this->addFlash('success', 'Dividende enregistré pour "' . $asset->getTicker() . '".');

            return $this->redirectToRoute('app_asset_show', ['id' => $asset->getId()]);
        }

        return $this->render('finance/asset/dividend.html.twig', [
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

    #[Route('/{assetId}/entries/{entryId}/delete', name: 'entry_delete', methods: ['POST'], requirements: ['assetId' => '\d+', 'entryId' => '\d+'])]
    public function deleteEntry(Request $request, int $assetId, int $entryId): Response
    {
        $entry = $this->assetEntryRepository->find($entryId);

        if (!$entry || $entry->getAsset()->getId() !== $assetId) {
            throw $this->createNotFoundException('Entry not found.');
        }

        $this->denyAccessUnlessGranted('EDIT', $entry->getSpace());

        if (!$this->isCsrfTokenValid('asset_entry_delete_' . $entry->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->assetEntryService->delete($entry);
        $this->addFlash('success', 'Opération supprimée.');

        return $this->redirectToRoute('app_asset_show', ['id' => $assetId]);
    }
}
