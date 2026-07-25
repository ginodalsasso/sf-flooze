<?php

declare(strict_types=1);

namespace App\Controller\Dashboard;

use App\Controller\ActiveSpaceControllerTrait;
use App\Entity\User;
use App\Service\Dashboard\DashboardService;
use App\Service\Space\SpaceResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    use ActiveSpaceControllerTrait;

    public function __construct(
        private readonly SpaceResolver $spaceResolver,
        private readonly DashboardService $dashboardService,
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
        ]);
    }
}
