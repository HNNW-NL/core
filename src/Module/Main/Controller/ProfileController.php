<?php

namespace App\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Module\Main\DTO\UpdateLocationDto;
use App\Module\Main\Service\LocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    private const DEV_ACCOUNT_ID = '018f4a2b-3c4d-7e6f-8a9b-0c1d2e3f4a5b';

    #[Route('/dev/set-user', name: 'dev_set_user')]
    public function devSetUser(SessionInterface $session): Response
    {
        $session->set('dev_account_id', self::DEV_ACCOUNT_ID);
        return new Response('✅ Dev user set! <a href="/profile">Go to profile</a>');
    }

    private function getAccount(SessionInterface $session, EntityManagerInterface $em): ?Account
    {
        $account = $this->getUser();

        if (!$account && $session->get('dev_account_id')) {
            $account = $em->getRepository(Account::class)
                ->find($session->get('dev_account_id'));
        }

        return $account;
    }

    #[Route('/profile', name: 'app_profile')]
    public function index(SessionInterface $session, EntityManagerInterface $em): Response
    {
        $account = $this->getAccount($session, $em);
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
        EntityManagerInterface $em,
        LocationService $locationService
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $dto = UpdateLocationDto::fromArray($data);
            $account = $this->getAccount($session, $em);

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