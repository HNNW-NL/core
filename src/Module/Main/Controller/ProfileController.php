<?php

namespace App\Module\Main\Controller;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
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

    #[Route('/profile/{slug}', name: 'main.profile', methods: ['GET'])]
    public function viewProfile(string $slug): Response
    {
        /*
         * TODO: Databasekoppeling activeren zodra er accounts bestaan.
         *
         * De publieke profielslug is altijd de username van het account.
         *
         * $account = $entityManager->getRepository(Account::class)->findOneBy([
         *     'username' => $slug,
         * ]);
         *
         * if ($account === null) {
         *     throw $this->createNotFoundException('Gebruiker niet gevonden.');
         * }
         *
         * $profile = $account->getProfile();
         *
         * if ($profile === null) {
         *     throw $this->createNotFoundException('Profiel niet gevonden.');
         * }
         *
         * return $this->render('pages/main/profile.html.twig', [
         *     'slug' => $account->getUsername(),
         *     'profile' => $profile,
         *     'guest_location' => $profile->getLocation(),
         * ]);
         */

        // Tijdelijke weergave zonder databasegegevens.
        return $this->render('pages/main/profile.html.twig', [
            'slug' => $slug,
            'profile' => null,
            'guest_location' => null,
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
