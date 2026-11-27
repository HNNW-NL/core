<?php

namespace App\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Event;
use App\Enum\ProfileVisibilityPermission;
use App\Enum\VisibilityLevel;
use App\Repository\Account\AccountRepository;
use App\Repository\Project\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'main.')]
final class MainController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $response = $this->render('pages/main/index.html.twig', [
            'publicProjects' => $projectRepository->findPublicProjects(6),
        ]);
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

        $settings = $account->getSetting();
        $canViewHeadline = $isOwner
            || $settings?->getSectionVisibility(ProfileVisibilityPermission::HEADLINE) === VisibilityLevel::PUBLIC;
        $canViewSkills = $isOwner
            || $settings?->getSectionVisibility(ProfileVisibilityPermission::SKILLS) === VisibilityLevel::PUBLIC;
        $canViewExperience = $isOwner
            || $settings?->getSectionVisibility(ProfileVisibilityPermission::EXPERIENCE) === VisibilityLevel::PUBLIC;

        $displayName = trim((string) $profile->getDisplayName());
        if ($displayName === '') {
            $displayName = trim((string) $profile->getFirstName() . ' ' . (string) $profile->getLastName());
        }
        if ($displayName === '') {
            $displayName = (string) $account->getUsername();
        }

        $skills = [];
        if ($canViewSkills) {
            foreach ($profile->getProfileSkills() as $profileSkill) {
                $skill = $profileSkill->getSkill();
                if ($skill !== null) {
                    $skills[] = [
                        'name' => $skill->getName(),
                        'proficiency' => $profileSkill->getProficiencyLevel()?->value,
                    ];
                }
            }
        }

        $experiences = [];
        if ($canViewExperience) {
            foreach ($profile->getExperiences() as $experience) {
                if ($experience->getDeletedAt() !== null) {
                    continue;
                }

                $experiences[] = [
                    'organisation' => $experience->getDisplayOrganisationName(),
                    'title' => $experience->getJobTitle(),
                    'description' => $experience->getDescription(),
                    'startDate' => $experience->getStartDate(),
                    'endDate' => $experience->getEndDate(),
                    'isCurrent' => $experience->isCurrent(),
                ];
            }
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
                'biography' => $canViewHeadline ? $profile->getDescription() : null,
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
    public function events(EntityManagerInterface $em): Response
    {
        $events = $em->getRepository(Event::class)->findBy(
            ['deletedAt' => null],
            ['startDatetime' => 'ASC']
        );

        return $this->render('pages/main/events/index.html.twig', ['events' => $events]);
    }

    #[Route('/events/{slug}', name: 'event', methods: ['GET'])]
    public function viewEvent(string $slug, EntityManagerInterface $em): Response
    {
        $event = $em->getRepository(Event::class)->findOneBy([
            'slug' => $slug,
            'deletedAt' => null,
        ]);

        if ($event === null) {
            throw $this->createNotFoundException('Evenement niet gevonden.');
        }

        return $this->render('pages/main/events/detail.html.twig', [
            'slug' => $slug,
            'event' => $event,
        ]);
    }
}
