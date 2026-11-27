<?php

namespace App\Module\Main\Controller;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use App\Module\Main\DTO\UpdateLocationDto;
use App\Module\Main\Service\LocationService;
use App\Module\Main\Service\PublicProfileService;
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
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->redirectToRoute('main.profile', ['slug' => $this->getAccount()->getUsername()]);
    }

    #[Route('/profile/{slug}', name: 'main.profile', methods: ['GET'])]
    public function viewProfile(string $slug, Request $request, PublicProfileService $profiles): Response
    {
        $positionPages = [];
        foreach ($request->query->keys() as $key) {
            if (preg_match('/\A(positions_[a-f0-9]{12})_page\z/', $key, $match)) {
                $positionPages[$match[1]] = $request->query->getInt($key, 1);
            }
        }
        $response = $this->render('pages/main/profile.html.twig', [
            'profileView' => $profiles->load($slug, $this->getAccount(), $request->query->getInt('projects_page', 1), [
                ...$positionPages,
                'receivedRecommendations' => $request->query->getInt('receivedRecommendations_page', 1),
                'givenRecommendations' => $request->query->getInt('givenRecommendations_page', 1),
                'experience' => $request->query->getInt('experience_page', 1),
                'education' => $request->query->getInt('education_page', 1),
                'certificates' => $request->query->getInt('certificates_page', 1),
                'skills' => $request->query->getInt('skills_page', 1),
                'socialLinks' => $request->query->getInt('socialLinks_page', 1),
            ]),
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
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
