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
    public const PROJECT_PAGE_SIZE = 20;
    public const RECOMMENDATION_PAGE_SIZE = 10;

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
        if (!$this->canView($profile, $viewer)) {
            return ['name' => 'Afgeschermd profiel', 'username' => null, 'avatar' => null];
        }
        $name = trim((string) $profile->getDisplayName()) ?: trim($profile->getFirstName().' '.$profile->getLastName());
        return [
            'name' => $name !== '' ? $name : (string) $profile->getAccount()?->getUsername(),
            'username' => $profile->getAccount()->getUsername(),
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

    public function load(
        string $username,
        ?Account $viewer,
        int $requestedProjectPage = 1,
        int $requestedReceivedPage = 1,
        int $requestedGivenPage = 1,
    ): array {
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
            'emailVerified' => $account->getEmailVerifiedAt() !== null,
            'sections' => [
                'experience' => $visible(ProfileVisibilityPermission::EXPERIENCE),
                'education' => $visible(ProfileVisibilityPermission::EDUCATION),
                'certificates' => $visible(ProfileVisibilityPermission::CERTIFICATIONS),
                'skills' => $visible(ProfileVisibilityPermission::SKILLS),
            ],
            'skills' => [], 'experience' => [], 'experienceGroups' => [], 'education' => [], 'certificates' => [], 'socialLinks' => [],
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
                ->select('record.id, record.jobTitle, record.description, record.startDate, record.endDate, record.externalOrganisationName, organisation.id AS organisationId, organisation.name AS organisationName, organisation.logoUrl AS organisationLogo')
                ->from(ProfileExperience::class, 'record')->leftJoin('record.organisation', 'organisation')
                ->andWhere('record.profile = :profile')->andWhere('record.deletedAt IS NULL')
                ->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('record.startDate', 'DESC')->addOrderBy('record.id', 'DESC')->getQuery()->getArrayResult();
            foreach ($data['experience'] as &$experience) {
                $experience['organisationLogo'] = self::safeImageUrl($experience['organisationLogo']);
            }
            unset($experience);
            $groups = [];
            foreach ($data['experience'] as $experience) {
                $company = trim((string) ($experience['organisationName'] ?: $experience['externalOrganisationName']));
                $key = $experience['organisationId'] !== null
                    ? 'org:'.$experience['organisationId']
                    : ($company !== '' ? 'external:'.mb_strtolower($company) : 'record:'.$experience['id']);
                $groups[$key] ??= ['company' => $company !== '' ? $company : null, 'logo' => $experience['organisationLogo'], 'positions' => []];
                $groups[$key]['positions'][] = [
                    'title' => $experience['jobTitle'], 'description' => $experience['description'], 'type' => '', 'location' => '',
                    'period' => $experience['startDate']->format('m-Y').' – '.($experience['endDate']?->format('m-Y') ?? 'heden'),
                ];
            }
            $data['experienceGroups'] = array_values($groups);
        }
        if ($visible(ProfileVisibilityPermission::EDUCATION)) {
            foreach ($this->em->getRepository(ProfileEducation::class)->findBy(['profile' => $profile], ['startDate' => 'DESC', 'id' => 'DESC']) as $education) {
                $data['education'][] = [
                    'school' => $education->getInstitutionName(), 'degree' => $education->getDegree(),
                    'fieldOfStudy' => $education->getFieldOfStudy(),
                    'logo' => self::safeImageUrl($education->getOrganisation()?->getLogoUrl()),
                    'startDate' => $education->getStartDate(), 'endDate' => $education->getEndDate(),
                    'description' => $education->getDescription(),
                ];
            }
        }
        if ($visible(ProfileVisibilityPermission::CERTIFICATIONS)) {
            foreach ($this->em->getRepository(ProfileCertification::class)->findBy(['profile' => $profile], ['issuedOn' => 'DESC', 'id' => 'DESC']) as $certificate) {
                $data['certificates'][] = [
                    'title' => $certificate->getName(), 'issuer' => $certificate->getIssuer(),
                    'issuedOn' => $certificate->getIssuedOn(), 'expiresOn' => $certificate->getExpiresOn(),
                    'credential' => $certificate->getCredentialId(),
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

        $received = $this->recommendations($profile, $viewer, 'profile', 'reviewerProfile', $requestedReceivedPage);
        $data['receivedRecommendations'] = $received['items'];
        $data['receivedRecommendationCount'] = $received['count'];
        $data['receivedRecommendationPages'] = $received['pages'];
        $data['receivedRecommendationPage'] = $received['page'];

        $given = $this->recommendations($profile, $viewer, 'reviewerProfile', 'profile', $requestedGivenPage);
        $data['givenRecommendations'] = $given['items'];
        $data['givenRecommendationCount'] = $given['count'];
        $data['givenRecommendationPages'] = $given['pages'];
        $data['givenRecommendationPage'] = $given['page'];

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
        $data['projectPages'] = max(1, (int) ceil($data['publicProjectCount'] / self::PROJECT_PAGE_SIZE));
        $data['projectPage'] = min($data['projectPages'], max(1, $requestedProjectPage));
        $data['projects'] = $projects->select('DISTINCT project.title, project.slug')
            ->orderBy('project.title', 'ASC')->addOrderBy('project.slug', 'ASC')
            ->setFirstResult(($data['projectPage'] - 1) * self::PROJECT_PAGE_SIZE)->setMaxResults(self::PROJECT_PAGE_SIZE)
            ->getQuery()->getArrayResult();
        return $data;
    }

    /**
     * Openbare aanbevelingen van of voor dit profiel, gepagineerd.
     * De privacyvoorwaarden (zelfde regels als canView()) zitten in de query,
     * zodat tellen en limiteren alleen zichtbare aanbevelingen meetelt.
     *
     * @param string $ownRelation    relatie naar het bekeken profiel ('profile' = ontvangen, 'reviewerProfile' = gegeven)
     * @param string $personRelation relatie naar de andere partij
     *
     * @return array{items: list<array<string, mixed>>, count: int, pages: int, page: int}
     */
    private function recommendations(Profile $profile, ?Account $viewer, string $ownRelation, string $personRelation, int $requestedPage): array
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Review::class, 'review')
            ->innerJoin('review.'.$personRelation, 'person')
            ->innerJoin('person.account', 'personAccount')
            ->leftJoin('personAccount.setting', 'personSetting')
            ->andWhere('review.'.$ownRelation.' = :profile')->setParameter('profile', $profile->getId(), 'ulid')
            ->andWhere('review.isPublic = true')
            ->andWhere('review.deletedAt IS NULL')
            ->andWhere('person.deletedAt IS NULL')
            ->andWhere('personAccount.deletedAt IS NULL');
        if ($viewer !== null) {
            $qb->andWhere('personSetting.profileVisibility = :public OR personAccount.id = :viewer')
                ->setParameter('viewer', $viewer->getId(), 'ulid');
        } else {
            $qb->andWhere('personSetting.profileVisibility = :public');
        }
        $qb->setParameter('public', 'public');

        $count = (int) (clone $qb)->select('COUNT(review.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($count / self::RECOMMENDATION_PAGE_SIZE));
        $page = min($pages, max(1, $requestedPage));

        $reviews = $qb->select('review', 'person', 'personAccount', 'personSetting')
            ->orderBy('review.id', 'DESC')
            ->setFirstResult(($page - 1) * self::RECOMMENDATION_PAGE_SIZE)
            ->setMaxResults(self::RECOMMENDATION_PAGE_SIZE)
            ->getQuery()->getResult();

        $items = [];
        foreach ($reviews as $review) {
            $person = $personRelation === 'reviewerProfile' ? $review->getReviewerProfile() : $review->getProfile();
            $summary = $this->summary($person, $viewer);
            $items[] = [
                'name' => $summary['name'], 'initials' => mb_substr($summary['name'], 0, 1),
                'headline' => $viewer?->getId() == $person->getAccount()->getId()
                    || $person->getAccount()->getSetting()?->getSectionVisibility(ProfileVisibilityPermission::HEADLINE) === VisibilityLevel::PUBLIC
                    ? $person->getHeadline() : null,
                'date' => $review->getCreatedAt(), 'text' => $review->getMessage(),
            ];
        }

        return ['items' => $items, 'count' => $count, 'pages' => $pages, 'page' => $page];
    }
}