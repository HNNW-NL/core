<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\ProfileSkill;
use App\Entity\Account\ProfileEducation;
use App\Entity\Account\ProfileCertification;
use App\Entity\Account\ProfileSocialLink;
use App\Entity\Account\Review;
use App\Entity\Project\Project;
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
        $name = trim((string) $profile->getDisplayName()) ?: trim($profile->getFirstName().' '.$profile->getLastName());
        return [
            'name' => $name !== '' ? $name : (string) $profile->getAccount()?->getUsername(),
            'username' => $this->canView($profile, $viewer) ? $profile->getAccount()->getUsername() : null,
            'avatar' => self::safeImageUrl($profile->getAvatarUrl()),
        ];
    }

    private static function safeImageUrl(?string $url): ?string
    {
        if ($url !== null && preg_match('~\A/(?!/)[^\\\\\x00-\x20]*\z~', $url)) {
            return $url;
        }
        return self::safeUrl($url);
    }

    public static function safeUrl(?string $url): ?string
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
            'sections' => [
                'experience' => $visible(ProfileVisibilityPermission::EXPERIENCE),
                'education' => $visible(ProfileVisibilityPermission::EDUCATION),
                'certificates' => $visible(ProfileVisibilityPermission::CERTIFICATIONS),
                'skills' => $visible(ProfileVisibilityPermission::SKILLS),
            ],
            'skills' => [], 'experience' => [], 'education' => [], 'certificates' => [], 'socialLinks' => [],
            'receivedRecommendations' => [], 'givenRecommendations' => [], 'suggestions' => [],
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
                ->select('record.jobTitle, record.description, record.startDate, record.endDate, record.externalOrganisationName, organisation.name AS organisationName, organisation.logoUrl AS organisationLogo')
                ->from(ProfileExperience::class, 'record')->leftJoin('record.organisation', 'organisation')
                ->andWhere('record.profile = :profile')->andWhere('record.deletedAt IS NULL')
                ->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('record.startDate', 'DESC')->addOrderBy('record.id', 'DESC')->getQuery()->getArrayResult();
            foreach ($data['experience'] as &$experience) {
                $experience['organisationLogo'] = self::safeImageUrl($experience['organisationLogo']);
            }
            unset($experience);
        }
        if ($visible(ProfileVisibilityPermission::EDUCATION)) {
            foreach ($this->em->getRepository(ProfileEducation::class)->findBy(['profile' => $profile], ['startDate' => 'DESC', 'id' => 'DESC']) as $education) {
                $data['education'][] = [
                    'school' => $education->getInstitutionName(), 'degree' => $education->getDegree(),
                    'startDate' => $education->getStartDate(), 'endDate' => $education->getEndDate(),
                    'description' => $education->getDescription(),
                ];
            }
        }
        if ($visible(ProfileVisibilityPermission::CERTIFICATIONS)) {
            foreach ($this->em->getRepository(ProfileCertification::class)->findBy(['profile' => $profile], ['issuedOn' => 'DESC', 'id' => 'DESC']) as $certificate) {
                $data['certificates'][] = [
                    'title' => $certificate->getName(), 'issuer' => $certificate->getIssuer(),
                    'issuedOn' => $certificate->getIssuedOn(), 'credential' => $certificate->getCredentialId(),
                    'url' => self::safeUrl($certificate->getCredentialUrl()),
                ];
            }
        }
        if ($visible(ProfileVisibilityPermission::SOCIAL_LINKS)) {
            foreach ($this->em->getRepository(ProfileSocialLink::class)->findBy(['profile' => $profile], ['sortOrder' => 'ASC', 'id' => 'ASC']) as $link) {
                $url = self::safeUrl($link->getUrl());
                if ($url !== null) {
                    $data['socialLinks'][] = ['label' => $link->getLabel(), 'url' => $url];
                }
            }
        }
        foreach (['receivedRecommendations' => 'profile', 'givenRecommendations' => 'reviewerProfile'] as $key => $relation) {
            $reviews = $this->em->getRepository(Review::class)->findBy([
                $relation => $profile, 'isPublic' => true, 'deletedAt' => null,
            ], ['id' => 'DESC'], 10);
            foreach ($reviews as $review) {
                $person = $relation === 'profile' ? $review->getReviewerProfile() : $review->getProfile();
                if ($person === null || !$this->canView($person, $viewer)) {
                    continue;
                }
                $summary = $this->summary($person, $viewer);
                $data[$key][] = [
                    'name' => $summary['name'], 'initials' => mb_substr($summary['name'], 0, 1),
                    'headline' => $viewer?->getId() == $person->getAccount()->getId()
                        || $person->getAccount()->getSetting()?->getSectionVisibility(ProfileVisibilityPermission::HEADLINE) === VisibilityLevel::PUBLIC
                        ? $person->getHeadline() : null,
                    'date' => $review->getCreatedAt(), 'text' => $review->getMessage(),
                ];
            }
        }
        $suggestions = $this->em->createQueryBuilder()->select('account', 'profile', 'setting')
            ->from(Account::class, 'account')->innerJoin('account.profile', 'profile')->innerJoin('account.setting', 'setting')
            ->andWhere('account.deletedAt IS NULL')->andWhere('profile.deletedAt IS NULL')
            ->andWhere('setting.profileVisibility = :visibility')->setParameter('visibility', 'public')
            ->andWhere('account.id != :current')->setParameter('current', $account->getId(), 'ulid')
            ->orderBy('account.username', 'ASC')->setMaxResults(6)->getQuery()->getResult();
        foreach ($suggestions as $suggestion) {
            $suggestedProfile = $suggestion->getProfile();
            $summary = $this->summary($suggestedProfile, $viewer);
            $data['suggestions'][] = $summary + [
                'initials' => mb_substr($summary['name'], 0, 1),
                'headline' => $suggestion->getSetting()->getSectionVisibility(ProfileVisibilityPermission::HEADLINE) === VisibilityLevel::PUBLIC
                    ? $suggestedProfile->getHeadline() : null,
            ];
        }
        $projects = $this->em->createQueryBuilder()->from(Project::class, 'project')
            ->leftJoin('project.participants', 'participant', 'WITH',
                'participant.profile = :profile AND participant.joinedAt IS NOT NULL AND participant.leftAt IS NULL')
            ->leftJoin('participant.status', 'participantStatus')
            ->andWhere('project.deletedAt IS NULL')->andWhere('project.publishedAt IS NOT NULL')
            ->andWhere('project.visibility = :visibility')->setParameter('visibility', 'public')
            ->andWhere('project.ownerAccount = :account OR (participant.id IS NOT NULL AND LOWER(participantStatus.scope) = :scope AND LOWER(participantStatus.name) IN (:active))')
            ->setParameter('account', $account->getId(), 'ulid')->setParameter('profile', $profile->getId(), 'ulid')
            ->setParameter('scope', 'projectparticipant')->setParameter('active', ['active', 'actief']);
        $data['publicProjectCount'] = (int) (clone $projects)->select('COUNT(DISTINCT project.id)')->getQuery()->getSingleScalarResult();
        $data['projects'] = $projects->select('DISTINCT project.title, project.slug')
            ->orderBy('project.title', 'ASC')->addOrderBy('project.slug', 'ASC')->getQuery()->getArrayResult();
        return $data;
    }
}
