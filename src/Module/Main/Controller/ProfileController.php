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

    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
    /** @var Account|null $account */
        $account = $this->getUser();
        $profile = $account?->getProfile();
        $location = $profile?->getLocation();

        return $this->render('pages/main/profile.html.twig', [
            'profile' => $profile,
            'guest_location' => $location,
        ]);
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
            /** @var Account|null $account */
            $account = $this->getUser();
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
