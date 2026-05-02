<?php

declare(strict_types=1);

namespace App\Controller\Dashboard;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        /** @var User $user */ 
        $user = $this->getUser();
        $spaces = $user->getSpaces()->toArray();

        $activeSpaceId = $request->getSession()->get('flooze_active_space_id');
        $activeSpace = null;

        foreach ($spaces as $space) {
            if ($space->getId() === $activeSpaceId) {
                $activeSpace = $space;
                break;
            }
        }

        // If the active space is not found (e.g., it was deleted), default to the first available space
        if ($activeSpace === null && !empty($spaces)) {
            $activeSpace = $spaces[0];
            $request->getSession()->set('flooze_active_space_id', $activeSpace->getId());
        }

        return $this->render('dashboard/index.html.twig', [
            'spaces' => $spaces,
            'active_space' => $activeSpace,
        ]);
    }
}
