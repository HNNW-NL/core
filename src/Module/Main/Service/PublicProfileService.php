<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\ProfileSkill;
use App\Enum\ProfileVisibilityPermission;
use App\Enum\VisibilityLevel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicProfileService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function canView(Profile $profile, ?Account $viewer): bool
    {
        $account = $profile->getAccount();
        return $profile->getDeletedAt() === null && $account !== null && $account->getDeletedAt() === null
            && ($viewer?->getId() == $account->getId() || $account->getSetting()?->getProfileVisibility() === 'public');
    }

    public function summary(Profile $profile, ?Account $viewer): array
    {
        return [
            'name' => $profile->getDisplayName() ?: trim($profile->getFirstName().' '.$profile->getLastName()),
            'username' => $this->canView($profile, $viewer) ? $profile->getAccount()->getUsername() : null,
            'avatar' => self::safeImage($profile->getAvatarUrl()),
        ];
    }

    public static function safeImage(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        return preg_match('~\Ahttps?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public function load(string $username, ?Account $viewer): array
    {
        $account = $this->em->getRepository(Account::class)->findOneBy(['username' => $username, 'deletedAt' => null]);
        $profile = $account?->getProfile();
        if ($profile === null || !$this->canView($profile, $viewer)) {
            throw new NotFoundHttpException('Profiel niet beschikbaar.');
        }
        $owner = $viewer?->getId() == $account->getId();
        // Voor de biografie bestaat geen sectiebit; daarvoor geldt profile_visibility.
        $visible = static fn (?ProfileVisibilityPermission $section): bool => $owner
            || ($section === null
                ? $account->getSetting()?->getProfileVisibility() === 'public'
                : $account->getSetting()?->getSectionVisibility($section) === VisibilityLevel::PUBLIC);
        $data = $this->summary($profile, $viewer) + [
            'biography' => $visible(null) ? $profile->getDescription() : null,
            'headline' => $visible(ProfileVisibilityPermission::HEADLINE) ? $profile->getHeadline() : null,
            'location' => $visible(ProfileVisibilityPermission::LOCATION) ? $profile->getLocation() : null,
            'canEdit' => $owner,
            'skills' => [], 'experience' => [],
        ];
        if ($visible(ProfileVisibilityPermission::SKILLS)) {
            $data['skills'] = $this->em->createQueryBuilder()->select('skill.name')
                ->from(ProfileSkill::class, 'record')->innerJoin('record.skill', 'skill')
                ->andWhere('skill.active = true')
                ->andWhere('record.profile = :profile')->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('skill.name', 'ASC')->getQuery()->getArrayResult();
        }
        if ($visible(ProfileVisibilityPermission::EXPERIENCE)) {
            $data['experience'] = $this->em->createQueryBuilder()
                ->select('record.jobTitle, record.description, record.startDate, record.endDate, record.externalOrganisationName, organisation.name AS organisationName')
                ->from(ProfileExperience::class, 'record')->leftJoin('record.organisation', 'organisation')
                ->andWhere('record.profile = :profile')->andWhere('record.deletedAt IS NULL')
                ->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('record.startDate', 'DESC')->addOrderBy('record.id', 'DESC')->getQuery()->getArrayResult();
        }
        return $data;
    }
}
