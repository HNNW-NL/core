<?php

declare(strict_types=1);

namespace App\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Availability;
use App\Entity\Account\Notification;
use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\ProfileSkill;
use App\Entity\Account\Review;
use App\Entity\Account\SavedItem;
use App\Entity\Account\Skill;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Enum\AvailabilityType;
use App\Enum\DayOfWeek;
use App\Enum\SkillProficiencyLevel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

#[Route('/account', name: 'account.')]
final class AccountsController extends AbstractController
{
    private const DAYS = [
        DayOfWeek::MONDAY, DayOfWeek::TUESDAY, DayOfWeek::WEDNESDAY,
        DayOfWeek::THURSDAY, DayOfWeek::FRIDAY, DayOfWeek::SATURDAY,
        DayOfWeek::SUNDAY,
    ];

    private function account(): Account
    {
        $user = $this->getUser();
        if (!$user instanceof Account) {
            throw $this->createAccessDeniedException('Je moet ingelogd zijn.');
        }
        return $user;
    }

    private function profile(EntityManagerInterface $em): Profile
    {
        $profile = $em->getRepository(Profile::class)->findOneBy(['account' => $this->account()]);
        if (!$profile || $profile->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Profiel niet gevonden.');
        }
        return $profile;
    }

    private function settings(EntityManagerInterface $em): AccountSetting
    {
        $account = $this->account();
        $settings = $em->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);
        if (!$settings) {
            $settings = $this->createAccountSettings($em, $account);
        }
        return $settings;
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ongeldig CSRF-token.');
        }
    }

    private function date(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    #[Route('', name: 'home', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $account = $this->account();
        $profile = $em->getRepository(Profile::class)->findOneBy(['account' => $account]);
        return $this->render('pages/account-centre/index.html.twig', [
            'account' => $account,
            'profile' => $profile,
        ]);
    }

    #[Route('/applications', name: 'applications', methods: ['GET'])]
    public function applications(EntityManagerInterface $em): Response
    {
        $account = $this->getAndCheckAccount();

        $profile = $em->getRepository(Profile::class)->findOneBy(['account' => $account]);

        $applications = $em->getRepository(ProjectApplication::class)->findBy(
            ['profile' => $profile, 'deletedAt' => null],
            ['lastModified' => 'DESC', 'id' => 'DESC']
        );

        return $this->render('pages/account-centre/applications.html.twig', [
            'profile' => $profile,
            'applications' => $applications,
        ]);
    }

    #[Route('/applications/{id}/cancel', name: 'applications_cancel', methods: ['POST'])]
    public function applicationsCancel(
        string $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $profile = $this->profile($em);

        $application = $em->getRepository(ProjectApplication::class)->find($id);
        $account = $this->getAndCheckAccount();
        $profile = $em->getRepository(Profile::class)->findOneBy(['account' => $account]);

        if (!$application || $application->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Aanmelding niet gevonden.');
        }

        if ($application->getProfile()?->getId()->toString() !== $profile->getId()->toString()) {
            throw $this->createAccessDeniedException();
        }
        if (!$profile || $application->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze aanmelding.');
        }

        $this->csrf($request, 'cancel_application' . $id);

        $cancelled = $em->wrapInTransaction(
            function () use ($em, $application, $profile): bool {
                $project = $application->getProject();

                $em->lock($project, LockMode::PESSIMISTIC_WRITE);
                $em->refresh($application, LockMode::PESSIMISTIC_WRITE);

                if (
                    $application->getDeletedAt() !== null
                    || $application->getProfile()?->getId()->toString() !== $profile->getId()->toString()
                    || $application->getParticipant() !== null
                    || $application->getStatus()?->getName() !== 'send'
                ) {
                    return false;
                }

                $application->softDelete();

                return true;
            }
        );

        if ($cancelled) {
            $this->addFlash('success', 'Aanmelding ingetrokken.');
        } else {
            $this->addFlash(
                'error',
                'Alleen openstaande aanmeldingen kunnen worden ingetrokken.'
            );
        }
        return $this->redirectToRoute('account.applications');
    }

    #[Route('/availability', name: 'availability', methods: ['GET', 'POST'])]
    public function availability(Request $request, EntityManagerInterface $em): Response
    {

        $profile = $this->profile($em);
        if (!$profile) {
            $this->addFlash('error', 'Profiel niet gevonden.');
            return $this->redirectToRoute('account.home');
        }

        // POST: Schema opslaan in de `availabilities` tabel
        if ($request->isMethod('POST')) {
            $this->csrf($request, 'account_availability');

            $from = $this->date(
                $request->request->getString('start_date')
            );

            $untilInput = trim(
                $request->request->getString('end_date')
            );

            $until = $untilInput === ''
                ? null
                : $this->date($untilInput);

            if (
                !$from
                || ($untilInput !== '' && !$until)
                || ($until && $until < $from)
            ) {
                $this->addFlash('error', 'Ongeldige periode.');
                return $this->redirectToRoute('account.availability');
            }

            $raw = $request->request->all('days_config');
            $entries = [];

            foreach (self::DAYS as $i => $day) {
                $data = $raw[$i] ?? [];

                if (!is_array($data) || empty($data['enabled'])) {
                    continue;
                }

                $start = $data['start'] ?? '';
                $end = $data['end'] ?? '';

                if (
                    !is_string($start)
                    || !is_string($end)
                    || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)
                    || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)
                    || $start >= $end
                ) {
                    $this->addFlash(
                        'error',
                        'Ongeldige begin- of eindtijd.'
                    );

                    return $this->redirectToRoute('account.availability');
                }
                $entries[] = (new Availability())
                    ->setProfile($profile)
                    ->setAvailabilityType(AvailabilityType::AVAILABLE)
                    ->setDayOfWeek($day)
                    ->setValidFrom($from)
                    ->setValidUntil($until)
                    ->setStartTime(new \DateTimeImmutable($start))
                    ->setEndTime(new \DateTimeImmutable($end));
            }
            if ($entries === []) {
                $this->addFlash('error', 'Selecteer minstens één dag.');
                return $this->redirectToRoute('account.availability');
            }

            foreach ($entries as $entry) {
                $em->persist($entry);
            }

            $em->flush();

            $this->addFlash('success', 'Beschikbaarheid opgeslagen.');

            return $this->redirectToRoute(
                'account.availability',
                [],
                Response::HTTP_SEE_OTHER
            );
        }

        $records = $em->getRepository(Availability::class)->findBy(
            [
                'profile' => $profile,
                'deletedAt' => null,
            ],
            [
                'validFrom' => 'DESC',
                'id' => 'DESC',
            ]
        );

        $names = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];

        $short = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];

        $default = [];

        foreach ($names as $i => $name) {
            $default[$i] = [
                'name' => $name,
                'enabled' => $i < 5,
                'start' => '08:00',
                'end' => '17:00',
            ];
        }

        $items = [];

        foreach ($records as $record) {
            $day = array_search(
                $record->getDayOfWeek(),
                self::DAYS,
                true
            );

            $items[] = [
                'id' => $record->getId()->toString(),
                'baseType' => $record->getAvailabilityType()?->value,
                'daysSummary' => $day === false ? '' : $short[$day],
                'startDate' => $record->getValidFrom(),
                'endDate' => $record->getValidUntil(),
                'startTime' => $record->getStartTime(),
                'endTime' => $record->getEndTime(),
                'createdAt' => $record->getCreatedAt(),
            ];
        }

        return $this->render(
            'pages/account-centre/availability.html.twig',
            [
                'profile' => $profile,
                'availabilities' => $items,
                'defaultSchedule' => $default,
            ]
        );
    }

    #[Route('/availability/{id}/delete', name: 'availability_delete', methods: ['POST'])]
    public function deleteAvailability(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $item = $em->getRepository(Availability::class)->find($id);
        if (!$item || $item->getDeletedAt() !== null) {
            throw $this->createNotFoundException();
        }
        if ($item->getProfile()?->getId()->toString() !== $this->profile($em)->getId()->toString()) {
            throw $this->createAccessDeniedException();
        }
        $this->csrf($request, 'delete_availability' . $id);
        $item->softDelete();
        $em->flush();
        return $this->redirectToRoute('account.availability');
    }

    #[Route('/experience', name: 'experience', methods: ['GET', 'POST'])]
    public function experience(Request $request, EntityManagerInterface $em): Response
    {
        $profile = $this->profile($em);
        if (!$profile) {
            $this->addFlash('error', 'Profiel niet gevonden.');
            return $this->redirectToRoute('account.home');
        }

        if ($request->isMethod('POST')) {
            $this->csrf($request, 'account_experience');
            $title = trim((string) $request->request->get('job_title', ''));
            $organisation = trim((string) $request->request->get('organisation_name', ''));
            $from = $this->date((string) $request->request->get('start_date', ''));
            $current = $request->request->has('is_current');

            $endDateInput = trim(
                $request->request->getString('end_date')
            );

            $until = $current || $endDateInput === ''
                ? null
                : $this->date($endDateInput);

            if (
                $title === ''
                || $organisation === ''
                || !$from
                || (!$current && $endDateInput !== '' && !$until)
                || ($until && $until < $from)
            ) {
                $this->addFlash('error', 'Controleer de verplichte velden en datums.');
            } else {
                $experience = (new ProfileExperience())
                    ->setProfile($profile)
                    ->setJobTitle($title)
                    ->setExternalOrganisationName($organisation)
                    ->setStartDate($from)
                    ->setEndDate($until)
                    ->setIsCurrent($current)
                    ->setDescription(trim((string) $request->request->get('description', '')) ?: null);
                $em->persist($experience);
                $em->flush();
                $this->addFlash('success', 'Werkervaring toegevoegd.');
            }
            return $this->redirectToRoute('account.experience');
        }
        return $this->render('pages/account-centre/experience.html.twig', [
            'experiences' => $em->getRepository(ProfileExperience::class)->findBy(
                ['profile' => $profile, 'deletedAt' => null], ['startDate' => 'DESC']
            ),
        ]);
    }

    #[Route('/experience/{id}/modify', name: 'modify_experience', methods: ['GET', 'POST'])]
    public function modifyExperience(
        string $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $experience = $em->getRepository(ProfileExperience::class)->find($id);

        if (!$experience || $experience->getDeletedAt() !== null) {
            throw $this->createNotFoundException();
        }

        if ($experience->getProfile()?->getId()->toString() !== $this->profile($em)->getId()->toString()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('pages/account-centre/modify-experience.html.twig', [
            'experience' => $experience,
        ]);
    }

    #[Route('/experience/{id}/delete', name: 'experience_delete', methods: ['POST'])]
    public function deleteExperience(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $account = $this->getAndCheckAccount();

        $experience = $em->getRepository(ProfileExperience::class)->find($id);
        if (!$experience || $experience->getDeletedAt() !== null) {
            throw $this->createNotFoundException();
        }
        if ($experience->getProfile()?->getId()->toString() !== $this->profile($em)->getId()->toString()) {
            throw $this->createAccessDeniedException();
        }

        $profile = $em->getRepository(Profile::class)->findOneBy(['account' => $account]);

        if (!$profile || $experience->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze ervaring.');
        }

        $this->csrf($request, 'delete_experience' . $id);
        $experience->softDelete();
        $em->flush();

        return $this->redirectToRoute('account.experience');
    }

    #[Route('/modify', name: 'modify', methods: ['GET', 'POST'])]
    public function modify(Request $request, EntityManagerInterface $em): Response
    {
        $account = $this->account();
        $profile = $this->profile($em);

        $formData = [
            'username' => $account->getUsername(),
            'email' => $account->getEmail(),
            'first_name' => $profile?->getFirstName() ?? '',
            'last_name' => $profile?->getLastName() ?? '',
            'display_name' => $profile?->getDisplayName() ?? '',
            'avatar_url' => $profile?->getAvatarUrl() ?? '',
            'location' => $profile?->getLocation() ?? '',
            'bio' => $profile?->getDescription() ?? '',
        ];

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$profile) {
                $this->addFlash('error', 'Profiel niet gevonden.');

                return $this->redirectToRoute('account.modify');
            }

            $this->csrf($request, 'account_modify');

            foreach ($formData as $field => $default) {
                $formData[$field] = trim((string) $request->request->get($field, ''));
            }

            $username = $formData['username'];
            $firstName = $formData['first_name'];
            $lastName = $formData['last_name'];
            $displayName = $this->nullableTrim($formData['display_name']);
            $avatarUrl = $formData['avatar_url'];
            $location = $this->nullableTrim($formData['location']);
            $bio = $this->nullableTrim($formData['bio']);

            if (empty($username) || mb_strlen($username) > 12) {
                $errors['username'] = 'Username moet ingevuld zijn en mag maximaal 12 tekens bevatten.';
            } elseif ($username !== $account->getUsername()) {
                $existingAccount = $em->getRepository(Account::class)
                    ->findOneBy(['username' => $username]);

                if ($existingAccount) {
                    $errors['username'] = 'Deze username is al in gebruik.';
                }
            }

            if (empty($firstName) || mb_strlen($firstName) > 50) {
                $errors['first_name'] = 'Voornaam moet ingevuld zijn en mag maximaal 50 tekens bevatten.';
            }

            if (empty($lastName) || mb_strlen($lastName) > 50) {
                $errors['last_name'] = 'Achternaam moet ingevuld zijn en mag maximaal 50 tekens bevatten.';
            }

            if ($displayName !== null && mb_strlen($displayName) > 50) {
                $errors['display_name'] = 'Display name mag maximaal 50 tekens bevatten.';
            }

            if (empty($avatarUrl)) {
                $avatarUrl = null;
            } else {
                $parsedUrl = parse_url($avatarUrl);

                if (
                    mb_strlen($avatarUrl) > 255 ||
                    !filter_var($avatarUrl, FILTER_VALIDATE_URL) ||
                    !in_array(
                        strtolower($parsedUrl['scheme'] ?? ''),
                        ['http', 'https'],
                        true
                    ) ||
                    empty($parsedUrl['host']) ||
                    !filter_var($parsedUrl['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
                ) {
                    $errors['avatar_url'] = 'Voer een geldige HTTP- of HTTPS-avatar-URL in van maximaal 255 tekens.';
                }
            }

            if ($location !== null && mb_strlen($location) > 50) {
                $errors['location'] = 'Location mag maximaal 50 tekens bevatten.';
            }

            if ($bio !== null && mb_strlen($bio) > 250) {
                $errors['bio'] = 'Bio mag maximaal 250 tekens bevatten.';
            }

            if (empty($errors)) {
                if ($username !== $account->getUsername()) {
                    $account->setUsername($username);
                }

                $profile->setFirstName($firstName);
                $profile->setLastName($lastName);
                $profile->setDisplayName($displayName);
                $profile->setDescription($bio);
                $profile->setAvatarUrl($avatarUrl);
                $profile->setLocation($location);

                if (method_exists($profile, 'setLastModified')) {
                    $profile->setLastModified(new \DateTimeImmutable());
                }

                try {
                    $em->flush();
                } catch (UniqueConstraintViolationException $e) {
                    $errors['username'] = 'Deze username is al in gebruik.';

                    return $this->render('pages/account-centre/modify.html.twig', [
                        'account' => $account,
                        'profile' => $profile,
                        'formData' => $formData,
                        'errors' => $errors,
                    ]);
                }

                $this->addFlash('success', 'Je wijzigingen zijn succesvol opgeslagen!');

                return $this->redirectToRoute('account.modify');
            }
        }

        return $this->render('pages/account-centre/modify.html.twig', [
            'account' => $account,
            'profile' => $profile,
            'formData' => $formData,
            'errors' => $errors,
        ]);
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request, EntityManagerInterface $em): Response
    {
        $account = $this->getAndCheckAccount();
        $settings = $em->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);

        if (!$settings) {
            $settings = $this->createAccountSettings($em, $account);
        }

        if ($request->isMethod('POST')) {
            $this->csrf($request, 'account_notifications');
            $settings->setEmailNotificationsEnabled(
                $request->request->has('notify_projects')
            );
            $em->flush();
            $this->addFlash('success', 'Notificatievoorkeuren opgeslagen.');
            return $this->redirectToRoute('account.notifications');
        }
        return $this->render('pages/account-centre/notifications.html.twig', [
            'settings' => $settings,
            'notifications' => $em->getRepository(Notification::class)->findBy(
                ['account' => $this->account(), 'deletedAt' => null], ['createdAt' => 'DESC'], 50
            ),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'notification_read', methods: ['POST'])]
    public function readNotification(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $notification = $em->getRepository(Notification::class)->find($id);
        if (!$notification || $notification->getDeletedAt() !== null) {
            throw $this->createNotFoundException();
        }
        if ($notification->getAccount()?->getId()->toString() !== $this->account()->getId()->toString()) {
            throw $this->createAccessDeniedException();
        }
        $this->csrf($request, 'read_notification' . $id);
        $notification->markAsRead();
        $em->flush();
        return $this->redirectToRoute('account.notifications');
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(EntityManagerInterface $em): Response
    {
        $profile = $this->profile($em);
        return $this->render('pages/account-centre/projects.html.twig', [
            'participants' => $em->getRepository(ProjectParticipant::class)->findBy(['profile' => $profile]),
            'applications' => $em->getRepository(ProjectApplication::class)->findBy(['profile' => $profile, 'deletedAt' => null]),
        ]);
    }

    #[Route('/reputation', name: 'reputation', methods: ['GET'])]
    public function reputation(EntityManagerInterface $em): Response
    {
        $profile = $this->profile($em);
        return $this->render('pages/account-centre/reputation.html.twig', [
            'profile' => $profile,
            'reviews' => $em->getRepository(Review::class)->findBy(['profile' => $profile, 'deletedAt' => null]),
        ]);
    }

    #[Route('/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(EntityManagerInterface $em): Response
    {
        return $this->render('pages/account-centre/reviews.html.twig', [
            'reviews' => $em->getRepository(Review::class)->findBy(
                ['profile' => $this->profile($em), 'deletedAt' => null], ['createdAt' => 'DESC']
            ),
        ]);
    }

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function settingsPage(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $settings = $this->settings($em);

        if ($request->isMethod('POST')) {
            $this->csrf($request, 'account_settings');

            $language = trim($request->request->getString('language', 'nl-NL'));
            $theme = trim($request->request->getString('theme', 'dark'));
            $visibility = trim($request->request->getString('profile_visibility', 'private'));

            if ($language === 'nl') {
                $language = 'nl-NL';
            }

            if (
                !in_array($language, ['nl-NL', 'en'], true)
                || !in_array($theme, ['light', 'dark'], true)
                || !in_array($visibility, ['public', 'private'], true)
            ) {
                $this->addFlash('error', 'Ongeldige accountinstellingen.');

                return $this->redirectToRoute('account.settings');
            }

            $settings
                ->setLanguage($language)
                ->setTheme($theme)
                ->setProfileVisibility($visibility)
                ->setEmailNotificationsEnabled(
                    $request->request->getBoolean('email_notifications_enabled')
                );

            $em->flush();

            $this->addFlash('success', 'Accountinstellingen opgeslagen.');

            return $this->redirectToRoute('account.settings');
        }

        return $this->render('pages/account-centre/settings.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/signoff', name: 'signoff', methods: ['GET'])]
    public function signoff(): Response
    {
        return $this->render('pages/account-centre/signoff.html.twig');
    }

    #[Route('/skills', name: 'skills', methods: ['GET'])]
    public function skills(EntityManagerInterface $em): Response
    {
        $profile = $this->profile($em);
        return $this->render('pages/account-centre/skills.html.twig', [
            'skills' => $em->getRepository(ProfileSkill::class)->findBy(['profile' => $profile]),
            'availableSkills' => $em->getRepository(Skill::class)->findBy(['active' => true], ['name' => 'ASC']),
        ]);
    }

    #[Route('/skills', name: 'skills_save', methods: ['POST'])]
    public function saveSkill(Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request, 'account_skills');
        $profile = $this->profile($em);
        $skill = $em->getRepository(Skill::class)->find((string) $request->request->get('skill_id', ''));
        $level = SkillProficiencyLevel::tryFrom((string) $request->request->get('level', 'beginner'));
        if (!$skill || !$skill->isActive() || !$level) {
            throw $this->createNotFoundException('Skill of niveau niet gevonden.');
        }
        $entry = $em->getRepository(ProfileSkill::class)->findOneBy(['profile' => $profile, 'skill' => $skill]);
        if (!$entry) {
            $entry = (new ProfileSkill())->setProfile($profile)->setSkill($skill);
            $em->persist($entry);
        }
        $entry->setProficiencyLevel($level);
        $em->flush();
        return $this->redirectToRoute('account.skills');
    }

    #[Route('/skills/{id}/delete', name: 'skills_delete', methods: ['POST'])]
    public function deleteSkill(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $entry = $em->getRepository(ProfileSkill::class)->find($id);
        if (!$entry) {
            throw $this->createNotFoundException();
        }
        if ($entry->getProfile()?->getId()->toString() !== $this->profile($em)->getId()->toString()) {
            throw $this->createAccessDeniedException();
        }
        $this->csrf($request, 'delete_skill' . $id);
        $em->remove($entry);
        $em->flush();
        return $this->redirectToRoute('account.skills');
    }

    #[Route('/saved-items', name: 'saved_items', methods: ['GET'])]
    public function savedItems(EntityManagerInterface $em): Response
    {
        return $this->render('pages/account-centre/saved-items.html.twig', [
            'items' => $em->getRepository(SavedItem::class)->findBy(
                ['account' => $this->account()], ['createdAt' => 'DESC']
            ),
        ]);
    }

    #[Route('/saved-items/{id}/delete', name: 'saved_items_delete', methods: ['POST'])]
    public function deleteSavedItem(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $item = $em->getRepository(SavedItem::class)->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }
        if ($item->getAccount()?->getId()->toString() !== $this->account()->getId()->toString()) {
            throw $this->createAccessDeniedException();
        }
        $this->csrf($request, 'delete_saved_item' . $id);
        $em->remove($item);
        $em->flush();
        return $this->redirectToRoute('account.saved_items');
    }

    private function getAndCheckAccount(): Account
    {
        $account = $this->getUser();

        if (!$account instanceof Account) {
            throw $this->createAccessDeniedException();
        }
        return $account;
    }

    private function createAccountSettings($entityManager, $account): AccountSetting
    {
        $settings = new AccountSetting();
        $settings->setAccount($account);
        $settings->setLanguage('nl');
        $settings->setTheme('dark');
        $settings->setProfileVisibility('public');
        $settings->setEmailNotificationsEnabled(true);

        $entityManager->persist($settings);

        return $settings;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
