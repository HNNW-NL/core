<?php

namespace App\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Module\Main\DTO\UpdateLocationDto;
use App\Module\Main\Service\LocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    private function getAccount(): ?Account
    {
        $user = $this->getUser();

        return $user instanceof Account ? $user : null;
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        $account = $this->getAccount();
        $profile = $account?->getProfile();

        return $this->render('pages/main/profile.html.twig', [
            'slug' => $account?->getUsername() ?? 'profiel',
            'profile' => $profile,
            'guest_location' => $profile?->getLocation(),
        ]);
    }

    // De Profile-entity heeft nog geen slug. De route blijft bestaan,
    // maar toont geen willekeurig profiel totdat slug-resolutie is toegevoegd.
    #[Route('/profile/{slug}', name: 'main.profile', methods: ['GET'])]
    public function viewProfile(string $slug): Response
    {
        throw $this->createNotFoundException('Publieke profielslug is nog niet beschikbaar.');
    }

    #[Route('/profile/location', name: 'profile_update_location', methods: ['POST'])]
    public function updateLocation(
        Request $request,
        SessionInterface $session,
        LocationService $locationService
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $dto = UpdateLocationDto::fromArray($data);
            $account = $this->getAccount();

            $result = $locationService->updateLocation($account, $dto, $session);

            $statusCode = $result->success ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

            return $this->json($result->toArray(), $statusCode);

        } catch (\Exception $e) {
            error_log('Profile update error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'message' => 'Server fout',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
