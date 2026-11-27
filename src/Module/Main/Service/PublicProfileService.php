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
use App\Security\ProjectParticipation;
use App\Security\ProjectVisibility;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class PublicProfileService
{
    public const PROJECT_PAGE_SIZE = 20;
    public const SECTION_PAGE_SIZE = 5;

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
        // Develop supplies local image assets; no external image origins are configured as trusted.
        if ($url !== null && preg_match('~\A/(?!/)[^\\\\\x00-\x20]*\z~', $url)) {
            return $url;
        }
        return null;
    }

    public static function safeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        return preg_match('~\Ahttps?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    public function loadFromRequest(string $username, ?Account $viewer, Request $request): array
    {
        $pages = [];
        foreach (['experience', 'education', 'certificates', 'skills', 'socialLinks', 'receivedRecommendations', 'givenRecommendations'] as $key) {
            $pages[$key] = $request->query->getInt($key.'_page', 1);
        }
        foreach ($request->query->keys() as $key) {
            if (preg_match('/\A(positions_[a-f0-9]{12})_page\z/', $key, $match)) {
                $pages[$match[1]] = $request->query->getInt($key, 1);
            }
        }
        return $this->load($username, $viewer, $request->query->getInt('projects_page', 1), $pages);
    }

    public function load(string $username, ?Account $viewer, int $requestedProjectPage = 1, array $requestedSectionPages = []): array
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
            'emailVerified' => $account->getEmailVerifiedAt() !== null,
            'sections' => [
                'experience' => $visible(ProfileVisibilityPermission::EXPERIENCE),
                'education' => $visible(ProfileVisibilityPermission::EDUCATION),
                'certificates' => $visible(ProfileVisibilityPermission::CERTIFICATIONS),
                'skills' => $visible(ProfileVisibilityPermission::SKILLS),
            ],
            'skills' => [], 'experience' => [], 'experienceGroups' => [], 'education' => [], 'certificates' => [], 'socialLinks' => [],
            'receivedRecommendations' => [], 'givenRecommendations' => [], 'suggestions' => [],
            'topSkills' => [], 'pagination' => [],
        ];
        if ($visible(ProfileVisibilityPermission::SKILLS)) {
            $skills = $this->em->createQueryBuilder()
                ->from(ProfileSkill::class, 'record')->innerJoin('record.skill', 'skill')
                ->andWhere('skill.active = true')
                ->andWhere('record.profile = :profile')->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('record.featured', 'DESC')->addOrderBy('skill.name', 'ASC')->addOrderBy('record.id', 'ASC');
            $data['topSkills'] = (clone $skills)->select('skill.name')->andWhere('record.featured = true')
                ->setMaxResults(5)->getQuery()->getArrayResult();
            $data['skills'] = $this->sectionPage($skills, 'skills', $requestedSectionPages, $data)
                ->select('skill.name, record.proficiencyLevel, record.yearsOfExperience, record.featured')->getQuery()->getArrayResult();
            foreach ($data['skills'] as &$skill) {
                $level = $skill['proficiencyLevel'];
                $level = $level instanceof \BackedEnum ? $level->value : $level;
                $context = [];
                if ($level !== null) {
                    $context[] = match ($level) {
                        'beginner' => 'Beginner', 'intermediate' => 'Gemiddeld', 'advanced' => 'Gevorderd', 'expert' => 'Expert',
                        default => $level,
                    };
                }
                if ($skill['yearsOfExperience'] !== null) {
                    $context[] = $skill['yearsOfExperience'].' jaar ervaring';
                }
                $skill['context'] = $context === [] ? null : implode(' · ', $context);
            }
            unset($skill);
        }
        if ($visible(ProfileVisibilityPermission::EXPERIENCE)) {
            $data['experience'] = $this->experiencePage($profile, $requestedSectionPages, $data);
            foreach ($data['experience'] as &$experience) {
                $experience['organisationLogo'] = self::safeImageUrl($experience['organisationLogo']);
            }
            unset($experience);
            $groups = [];
            foreach ($data['experience'] as $experience) {
                $company = $experience['organisationName'] ?: $experience['externalOrganisationName'];
                $key = $experience['organisationId'] !== null ? 'org:'.$experience['organisationId']
                    : ($company ? 'external:'.$company : 'record:'.$experience['id']);
                $groups[$key] ??= ['company' => $company, 'logo' => $experience['organisationLogo'], 'positions' => [], 'paginationKey' => $experience['paginationKey'], 'total' => $experience['groupTotal']];
                $groups[$key]['positions'][] = [
                    'title' => $experience['jobTitle'], 'description' => $experience['description'],
                    'period' => $experience['startDate']->format('m-Y').' – '.($experience['endDate']?->format('m-Y') ?? 'heden'),
                ];
            }
            $data['experienceGroups'] = array_values($groups);
        }
        if ($visible(ProfileVisibilityPermission::EDUCATION)) {
            $educationQuery = $this->em->createQueryBuilder()->from(ProfileEducation::class, 'record')
                ->leftJoin('record.organisation', 'organisation')->andWhere('record.profile = :profile')
                ->setParameter('profile', $profile->getId(), 'ulid')->orderBy('record.startDate', 'DESC')->addOrderBy('record.id', 'DESC');
            foreach ($this->sectionPage($educationQuery, 'education', $requestedSectionPages, $data)->select('record', 'organisation')->getQuery()->getResult() as $education) {
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
            $certificateQuery = $this->em->createQueryBuilder()->from(ProfileCertification::class, 'record')
                ->andWhere('record.profile = :profile')->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('record.issuedOn', 'DESC')->addOrderBy('record.id', 'DESC');
            foreach ($this->sectionPage($certificateQuery, 'certificates', $requestedSectionPages, $data)->select('record')->getQuery()->getResult() as $certificate) {
                $data['certificates'][] = [
                    'title' => $certificate->getName(), 'issuer' => $certificate->getIssuer(),
                    'issuedOn' => $certificate->getIssuedOn(), 'expiresOn' => $certificate->getExpiresOn(),
                    'credential' => $certificate->getCredentialId(),
                    'url' => self::safeUrl($certificate->getCredentialUrl()),
                ];
            }
        }
        if ($visible(ProfileVisibilityPermission::SOCIAL_LINKS)) {
            $socialQuery = $this->em->createQueryBuilder()->from(ProfileSocialLink::class, 'record')
                ->andWhere('record.profile = :profile')->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('record.sortOrder', 'ASC')->addOrderBy('record.id', 'ASC');
            foreach ($this->sectionPage($socialQuery, 'socialLinks', $requestedSectionPages, $data)->select('record')->getQuery()->getResult() as $link) {
                $url = self::safeUrl($link->getUrl());
                if ($url !== null) {
                    $data['socialLinks'][] = ['label' => $link->getLabel(), 'url' => $url];
                }
            }
        }
        foreach (['receivedRecommendations' => 'profile', 'givenRecommendations' => 'reviewerProfile'] as $key => $relation) {
            $otherRelation = $relation === 'profile' ? 'reviewerProfile' : 'profile';
            $reviewQuery = $this->em->createQueryBuilder()->from(Review::class, 'record')
                ->innerJoin('record.'.$otherRelation, 'person')->innerJoin('person.account', 'otherAccount')
                ->leftJoin('otherAccount.setting', 'otherSetting')
                ->andWhere('record.'.$relation.' = :profile')->setParameter('profile', $profile->getId(), 'ulid')
                ->andWhere('record.isPublic = true')->andWhere('record.deletedAt IS NULL')
                ->andWhere('person.deletedAt IS NULL')->andWhere('otherAccount.deletedAt IS NULL')
                ->andWhere('otherSetting.profileVisibility = :public OR otherAccount.id = :viewer')
                ->setParameter('public', 'public')->setParameter('viewer', $viewer?->getId(), 'ulid')
                ->orderBy('record.id', 'DESC');
            $reviews = $this->sectionPage($reviewQuery, $key, $requestedSectionPages, $data)
                ->select('record', 'person', 'otherAccount', 'otherSetting')->getQuery()->getResult();
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
                'participant.profile = :profile')
            ->leftJoin('participant.status', 'participantStatus')
            ->andWhere('project.deletedAt IS NULL')->andWhere('project.publishedAt IS NOT NULL')
            ->andWhere('project.visibility = :visibility')->setParameter('visibility', 'public')
            ->andWhere('project.ownerAccount = :account OR (participant.id IS NOT NULL AND '.ProjectParticipation::predicate('participant', 'participantStatus').')')
            ->setParameter('account', $account->getId(), 'ulid')->setParameter('profile', $profile->getId(), 'ulid');
        ProjectVisibility::restrictQuery($projects, 'project');
        $data['publicProjectCount'] = (int) (clone $projects)->select('COUNT(DISTINCT project.id)')->getQuery()->getSingleScalarResult();
        $data['projectPages'] = max(1, (int) ceil($data['publicProjectCount'] / self::PROJECT_PAGE_SIZE));
        $data['projectPage'] = min($data['projectPages'], max(1, $requestedProjectPage));
        $data['projects'] = $projects->select('DISTINCT project.title, project.slug')
            ->orderBy('project.title', 'ASC')->addOrderBy('project.slug', 'ASC')
            ->setFirstResult(($data['projectPage'] - 1) * self::PROJECT_PAGE_SIZE)->setMaxResults(self::PROJECT_PAGE_SIZE)
            ->getQuery()->getArrayResult();
        return $data;
    }

    /** Paginate organisations first, then paginate positions within each selected organisation. */
    private function experiencePage(Profile $profile, array $requestedPages, array &$data): array
    {
        $db = $this->em->getConnection();
        $groupKey = "COALESCE('org:' || organisation_id::text, 'external:' || NULLIF(external_organisation_name, ''), 'record:' || id::text)";
        $base = " FROM profile_experiences WHERE profile_id = :profile AND deleted_at IS NULL GROUP BY ".$groupKey;
        $params = ['profile' => $profile->getId()];
        $types = ['profile' => 'ulid'];
        $total = (int) $db->fetchOne('SELECT COUNT(*) FROM (SELECT '.$groupKey.$base.') groups', $params, $types);
        $pages = max(1, (int) ceil($total / self::SECTION_PAGE_SIZE));
        $page = min($pages, max(1, (int) ($requestedPages['experience'] ?? 1)));
        $data['pagination']['experience'] = ['total' => $total, 'pages' => $pages, 'page' => $page];
        $groups = $db->fetchAllAssociative('SELECT '.$groupKey.' AS group_key, MAX(organisation_id::text) AS organisation_id, MAX(external_organisation_name) AS external_name, MAX(id::text) AS record_id'
            .$base.' ORDER BY MAX(start_date) DESC, group_key ASC LIMIT '.self::SECTION_PAGE_SIZE.' OFFSET '.(($page - 1) * self::SECTION_PAGE_SIZE), $params, $types);
        $rows = [];
        foreach ($groups as $group) {
            $query = $this->em->createQueryBuilder()->from(ProfileExperience::class, 'record')->leftJoin('record.organisation', 'organisation')
                ->andWhere('record.profile = :profile')->andWhere('record.deletedAt IS NULL')->setParameter('profile', $profile->getId(), 'ulid')
                ->orderBy('record.startDate', 'DESC')->addOrderBy('record.id', 'DESC');
            if ($group['organisation_id'] !== null) {
                $query->andWhere('record.organisation = :organisation')->setParameter('organisation', Ulid::fromRfc4122($group['organisation_id']), 'ulid');
            } elseif ($group['external_name'] !== null && $group['external_name'] !== '') {
                $query->andWhere('record.organisation IS NULL')->andWhere('record.externalOrganisationName = :name')->setParameter('name', $group['external_name']);
            } else {
                $query->andWhere('record.id = :id')->setParameter('id', Ulid::fromRfc4122($group['record_id']), 'ulid');
            }
            $key = 'positions_'.substr(hash('sha256', $group['group_key']), 0, 12);
            $items = $this->sectionPage($query, $key, $requestedPages, $data)
                ->select('record.id, record.jobTitle, record.description, record.startDate, record.endDate, record.externalOrganisationName, organisation.id AS organisationId, organisation.name AS organisationName, organisation.logoUrl AS organisationLogo')
                ->getQuery()->getArrayResult();
            foreach ($items as $item) {
                $rows[] = $item + ['paginationKey' => $key, 'groupTotal' => $data['pagination'][$key]['total']];
            }
        }
        return $rows;
    }

    /** Counts without hydrating records and limits every visible section before fetching its data. */
    private function sectionPage(QueryBuilder $query, string $section, array $requestedPages, array &$data): QueryBuilder
    {
        $total = (int) (clone $query)->select('COUNT(record.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / self::SECTION_PAGE_SIZE));
        $page = min($pages, max(1, (int) ($requestedPages[$section] ?? 1)));
        $data['pagination'][$section] = ['total' => $total, 'page' => $page, 'pages' => $pages];
        return $query->setFirstResult(($page - 1) * self::SECTION_PAGE_SIZE)->setMaxResults(self::SECTION_PAGE_SIZE);
    }
}
