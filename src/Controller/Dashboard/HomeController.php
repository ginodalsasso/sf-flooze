<?php

declare(strict_types=1);

namespace App\Controller\Dashboard;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\User;
use App\Enum\CurrencyEnum;
use App\Service\Dashboard\Contract\DashboardServiceInterface;
use App\Service\Space\Contract\SpaceResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly SpaceResolverInterface $spaceResolver,
        private readonly DashboardServiceInterface $dashboardService,
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $activeSpace = $this->resolveActiveSpace();
        if ($activeSpace instanceof RedirectResponse) {
            return $activeSpace;
        }

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/index.html.twig', [
            'spaces' => $user->getSpaces()->toArray(),
            'active_space' => $activeSpace,
            'summary' => $this->dashboardService->summarize($activeSpace),
            'currencies' => CurrencyEnum::cases(),
            'baseCurrency' => $activeSpace->getCurrency(),
            'quoteCurrency' => $this->quoteCurrency($activeSpace->getCurrency()),
        ]);
    }

    /** Target currency of the converter: preselecting the space currency on both sides would show nothing. */
    private function quoteCurrency(CurrencyEnum $base): CurrencyEnum
    {
        return $base === CurrencyEnum::EUR ? CurrencyEnum::USD : CurrencyEnum::EUR;
    }
}
