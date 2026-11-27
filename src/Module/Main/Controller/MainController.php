<?php

namespace App\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Repository\Account\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'main.')]
final class MainController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        $response = $this->render('pages/main/index.html.twig');
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('pages/main/about.html.twig');
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('pages/main/contact.html.twig');
    }

    #[Route('/profile/{slug}', name: 'profile', methods: ['GET'])]
    public function viewProfile(string $slug, AccountRepository $accountRepository): Response
    {
        $account = $accountRepository->findByUsername($slug);
        $profile = $account?->getProfile();

        if (
            !$account instanceof Account
            || $account->getDeletedAt() !== null
            || !$profile instanceof Profile
            || $profile->getDeletedAt() !== null
        ) {
            throw $this->createNotFoundException('Profiel niet gevonden.');
        }

        $viewer = $this->getUser();
        $isOwner = $viewer instanceof Account
            && (string) $viewer->getId() === (string) $account->getId();
        $isPublic = strtolower((string) $account->getSetting()?->getProfileVisibility()) === 'public';

        if (!$isOwner && !$isPublic) {
            throw $this->createNotFoundException('Profiel niet gevonden.');
        }

        $displayName = trim((string) $profile->getDisplayName());
        if ($displayName === '') {
            $displayName = trim((string) $profile->getFirstName() . ' ' . (string) $profile->getLastName());
        }
        if ($displayName === '') {
            $displayName = (string) $account->getUsername();
        }

        $skills = [];
        foreach ($profile->getProfileSkills() as $profileSkill) {
            $skill = $profileSkill->getSkill();
            if ($skill !== null) {
                $skills[] = [
                    'name' => $skill->getName(),
                    'proficiency' => $profileSkill->getProficiencyLevel(),
                ];
            }
        }

        $experiences = [];
        foreach ($profile->getExperiences() as $experience) {
            if ($experience->getDeletedAt() !== null) {
                continue;
            }

            $experiences[] = [
                'organisation' => $experience->getOrganisationName(),
                'title' => $experience->getJobTitle(),
                'description' => $experience->getDescription(),
                'startDate' => $experience->getStartDate(),
                'endDate' => $experience->getEndDate(),
                'isCurrent' => $experience->isCurrent(),
            ];
        }
        usort(
            $experiences,
            static fn (array $left, array $right): int => $right['startDate'] <=> $left['startDate'],
        );

        $response = $this->render('pages/main/public_profile.html.twig', [
            'profile' => [
                'displayName' => $displayName,
                'username' => $account->getUsername(),
                'avatarUrl' => $profile->getAvatarUrl() === 'none' ? null : $profile->getAvatarUrl(),
                'biography' => $profile->getDescription(),
                'skills' => $skills,
                'experiences' => $experiences,
            ],
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(): Response
    {
        return $this->render('pages/main/events/index.html.twig');
    }

    #[Route('/events/{slug}', name: 'event', methods: ['GET'])]
    public function viewEvent(string $slug): Response
    {
        return $this->render('pages/main/events/detail.html.twig', [
            "slug" => $slug,
        ]);
    }
}
