<?php

declare(strict_types=1);

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Service\Space\SpaceResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(private readonly SpaceResolver $spaceResolver) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $activeSpace = $this->spaceResolver->resolve($user);

        if ($activeSpace === null) {
            return $this->redirectToRoute('app_space_new');
        }

        return $this->render('dashboard/index.html.twig', [
            'spaces' => $user->getSpaces()->toArray(),
            'active_space' => $activeSpace,
        ]);
    }
}
