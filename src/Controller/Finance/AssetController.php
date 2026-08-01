<?php

declare(strict_types=1);

namespace App\Controller\Finance;

use App\Controller\ActiveSpaceControllerTrait;
use App\Dto\Finance\AssetEntryFilterDto;
use App\Dto\Finance\AssetEntryInputDto;
use App\Dto\Finance\AssetListItemDto;
use App\Entity\Asset;
use App\Enum\AssetEntryKindEnum;
use App\Form\Finance\AssetDividendFormType;
use App\Form\Finance\AssetFormType;
use App\Form\Finance\AssetSellFormType;
use App\Repository\Contract\AccountRepositoryInterface;
use App\Repository\Contract\AssetEntryRepositoryInterface;
use App\Repository\Contract\AssetRepositoryInterface;
use App\Service\Finance\Contract\AssetEntryServiceInterface;
use App\Service\Finance\Contract\AssetMetricsServiceInterface;
use App\Service\Finance\Contract\AssetServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/finance/assets', name: 'app_asset_')]
class AssetController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly AssetServiceInterface $assetService,
        private readonly AssetEntryServiceInterface $assetEntryService,
        private readonly AssetMetricsServiceInterface $assetMetricsService,
        private readonly AssetRepositoryInterface $assetRepository,
        private readonly AssetEntryRepositoryInterface $assetEntryRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly SpaceResolverInterface $spaceResolver,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $space = $this->resolveActiveSpace('VIEW');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        $assets = $this->assetRepository->findBySpace($space);
        $assetItems = array_map(
            fn (Asset $asset) => new AssetListItemDto(
                asset: $asset,
                metrics: $this->assetMetricsService->compute($asset),
            ),
            $assets,
        );

        return $this->render('finance/asset/index.html.twig', [
            'assetItems' => $assetItems,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Request $request, Asset $asset): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $asset->getSpace());

        // Only links feed this filter, so parsing the query string directly is enough here.
        $filter = new AssetEntryFilterDto();
        $filter->kind = AssetEntryKindEnum::tryFrom($request->query->getString('kind'));
        $filter->dateFrom = $this->parseDate($request->query->getString('date_from'));
        $filter->dateTo = $this->parseDate($request->query->getString('date_to'));

        return $this->render('finance/asset/show.html.twig', [
            'asset'      => $asset,
            'entries'    => $this->assetEntryRepository->findByAsset($asset, $filter),
            'metrics'    => $this->assetMetricsService->compute($asset),
            'filter'     => $filter,
            'assetKinds' => AssetEntryKindEnum::cases(),
        ]);
    }

    /** Null on anything that is not a plain Y-m-d date, so a forged param cannot break the page. */
    private function parseDate(string $value): ?\DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: null;
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $space = $this->resolveActiveSpace('EDIT');
        if ($space instanceof RedirectResponse) {
            return $space;
        }

        // An asset must be linked to an account. If the space has no account at
        // all, redirect to account creation before allowing any asset creation.
        if ($this->accountRepository->countBySpace($space) === 0) {
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
            $this->assetEntryService->recordEntry(AssetEntryInputDto::buy(
                asset: $asset,
                space: $space,
                date: $form->get('entryDate')->getData(),
                quantity: (string) $form->get('entryQuantity')->getData(),
                unitPrice: (string) $form->get('entryUnitPrice')->getData(),
                fxRate: (string) $form->get('entryFxRate')->getData(),
                fees: (string) $form->get('entryFees')->getData(),
                account: $form->get('account')->getData(),
                fundingAccount: $form->get('fundingAccount')->getData(),
            ));

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
                $entry = $this->assetEntryService->recordEntry(AssetEntryInputDto::sell(
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
                ));

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
            'form'    => $form,
            'asset'   => $asset,
            'metrics' => $this->assetMetricsService->compute($asset),
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
            $this->assetEntryService->recordEntry(AssetEntryInputDto::dividend(
                asset: $asset,
                space: $space,
                date: $form->get('date')->getData(),
                amount: (string) $form->get('amount')->getData(),
                fxRate: (string) $form->get('fxRate')->getData(),
                fees: (string) $form->get('fees')->getData(),
                account: $form->get('account')->getData(),
                fundingAccount: $form->get('fundingAccount')->getData(),
                note: $form->get('note')->getData(),
            ));

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

        if (!$entry || $entry->getAsset()->getId() !== $assetId || $entry->isDeleted()) {
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
