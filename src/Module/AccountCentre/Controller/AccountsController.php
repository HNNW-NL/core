<?php

namespace App\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\ProfileExperience;
use App\Entity\Project\ProjectApplication;
use App\Module\AccountCentre\DTO\AvailabilityDTO;
use App\Module\AccountCentre\Enum\AvailabilityType;
use App\Module\AccountCentre\Enum\DayOfWeek;
use App\Module\AccountCentre\Handler\CreateAvailabilityHandler;
use App\Repository\Account\AvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/account', name: 'account.')]
final class AccountsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/account-centre/index.html.twig');
    }

    #[Route('/applications', name: 'applications', methods: ['GET'])]
    public function applications(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn om je aanmeldingen te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);

        if (!$account) {
            $this->addFlash('error', 'Account niet gevonden.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);

        // Ophalen van de aanmeldingen MET verplichte 2e parameter (orderBy)
        $applications = $entityManager->getRepository(ProjectApplication::class)->findBy(
            ['profile' => $profile, 'deletedAt' => null],
            ['createdAt' => 'DESC']
        );

        return $this->render('pages/account-centre/applications.html.twig', [
            'profile' => $profile,
            'applications' => $applications,
        ]);
    }

    #[Route('/applications/{id}/cancel', name: 'applications_cancel', methods: ['POST'])]
    public function applicationsCancel(ProjectApplication $application, Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze actie uit te voeren.');
            return $this->redirectToRoute('auth.login');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        if (!$account) {
            $this->addFlash('error', 'Account niet gevonden.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile || $application->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze aanmelding.');
        }

        $csrfToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('cancel_application' . $application->getId()->toString(), $csrfToken)) {

            // Direct de softDelete aanroepen op de Application entiteit
            $application->softDelete();

            $entityManager->flush();

            $this->addFlash('success', 'Je aanmelding is succesvol geannuleerd.');
        } else {
            $this->addFlash('error', 'Ongeldig veiligheidstoken. Probeer het opnieuw.');
        }

        return $this->redirectToRoute('account.applications');
    }

      #[Route('/availability', name: 'availability', methods: ['GET', 'POST'])]
    public function availability(
        Request $request,
        EntityManagerInterface $entityManager,
        CreateAvailabilityHandler $createAvailabilityHandler,
        AvailabilityRepository $availabilityRepository,
    ): Response {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn om je beschikbaarheid te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        if (!$account) {
            $this->addFlash('error', 'Account niet gevonden.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile) {
            $this->addFlash('error', 'Profiel niet gevonden.');
            return $this->redirectToRoute('account.home');
        }

        $dayEnums = [
            0 => DayOfWeek::MONDAY,
            1 => DayOfWeek::TUESDAY,
            2 => DayOfWeek::WEDNESDAY,
            3 => DayOfWeek::THURSDAY,
            4 => DayOfWeek::FRIDAY,
            5 => DayOfWeek::SATURDAY,
            6 => DayOfWeek::SUNDAY,
        ];

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_token');

            if (!$this->isCsrfTokenValid('save_availability', $csrfToken)) {
                $this->addFlash('error', 'Ongeldig veiligheidstoken. Probeer het opnieuw.');
                return $this->redirectToRoute('account.availability');
            }

            $daysInput = $request->request->all('days_config');

            try {
                $validFrom = new \DateTimeImmutable(
                    (string) ($request->request->get('start_date') ?: 'today')
                );
                $validUntilInput = $request->request->get('end_date');
                $validUntil = $validUntilInput
                    ? new \DateTimeImmutable((string) $validUntilInput)
                    : null;

                $savedCount = 0;

                foreach ($dayEnums as $dayNumber => $dayOfWeek) {
                    $dayConfig = $daysInput[$dayNumber] ?? [];

                    if (!isset($dayConfig['enabled'])) {
                        continue;
                    }

                    $startTimeInput = $dayConfig['start'] ?? null;
                    $endTimeInput = $dayConfig['end'] ?? null;

                    if (!$startTimeInput || !$endTimeInput) {
                        continue;
                    }

                    $startTime = new \DateTimeImmutable((string) $startTimeInput);
                    $endTime = new \DateTimeImmutable((string) $endTimeInput);

                    if ($endTime <= $startTime) {
                        throw new \InvalidArgumentException(sprintf(
                            'De eindtijd van %s moet na de starttijd liggen.',
                            $dayOfWeek->value
                        ));
                    }

                    $dto = new AvailabilityDTO(
                        availabilityType: AvailabilityType::STANDARD_CONTRACTUAL,
                        validFrom: $validFrom,
                        validUntil: $validUntil,
                        dayOfWeek: $dayOfWeek,
                        startTime: $startTime,
                        endTime: $endTime,
                        note: null,
                    );

                    $createAvailabilityHandler->handle($dto, $profile, false);
                    $savedCount++;
                }

                if ($savedCount === 0) {
                    $this->addFlash('error', 'Selecteer minimaal één dag met geldige tijden.');
                    return $this->redirectToRoute('account.availability');
                }

                $entityManager->flush();
                $this->addFlash('success', 'Beschikbaarheid succesvol opgeslagen!');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Fout bij opslaan: ' . $e->getMessage());
            }

            return $this->redirectToRoute('account.availability');
        }
        
        try {
            $rows = $connection->fetchAllAssociative(
                'SELECT * FROM availabilities WHERE profile_id = :profileId AND deleted_at IS NULL ORDER BY start_date DESC',
                ['profileId' => $profile->getId()->toString()]
            );
        } catch (\Exception $e) {
            $rows = [];
        }

        $availabilities = [];
        $daysMapping = [0 => 'Ma', 1 => 'Di', 2 => 'Wo', 3 => 'Do', 4 => 'Vr', 5 => 'Za', 6 => 'Zo'];
        $typeMapping = ['FT' => 'Full-Time', 'PT' => 'Part-Time'];

        foreach ($rows as $row) {
            $rawValue = $row['availability_type'] ?? '';

            $structuredDays = [];
            foreach (range(0, 6) as $d) {
                $structuredDays[$d] = ['enabled' => false, 'start' => '08:00', 'end' => '17:00'];
            }

            $baseType = 'Standaard';

            if (str_contains($rawValue, '|')) {
                $parts = explode('|', $rawValue);
                $daysPart = $parts[0] ?? '';
                $timePart = $parts[1] ?? '08:00-17:00';
                $typePart = $parts[2] ?? 'FT';

                [$start, $end] = str_contains($timePart, '-') ? explode('-', $timePart, 2) : ['08:00', '17:00'];
                $baseType = $typeMapping[$typePart] ?? 'Standaard';

                $activeDaysArray = str_split($daysPart);
                foreach ($activeDaysArray as $dayNum) {
                    $dayNum = (int)$dayNum;
                    if ($dayNum >= 0 && $dayNum <= 6) {
                        $structuredDays[$dayNum] = [
                            'enabled' => true,
                            'start' => $start,
                            'end' => $end
                        ];
                    }
                }
            } else {
                foreach (range(0, 4) as $d) {
                    $structuredDays[$d]['enabled'] = true;
                }
                $baseType = !empty($rawValue) ? $rawValue : 'Standaard';
            }

            $activeDaysText = [];
            foreach ($structuredDays as $dayNum => $meta) {
                if ($meta['enabled']) {
                    $activeDaysText[] = $daysMapping[$dayNum];
                }
            }
            $daysSummary = !empty($activeDaysText) ? ' (' . implode(', ', $activeDaysText) . ')' : ' (Geen werkdagen)';

            $availabilities[] = (object) [
                'id' => $row['id'],
                'baseType' => $baseType,
                'daysSummary' => $daysSummary,
                'daysDetails' => $structuredDays,
                'blockedDates' => [],
                'hoursPerWeek' => $row['hours_per_week'],
                'startDate' => new \DateTimeImmutable($row['start_date']),
                'endDate' => $row['end_date'] ? new \DateTimeImmutable($row['end_date']) : null
            ];
        }

        $defaultSchedule = [];
        $fullNames = [0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday', 3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday', 6 => 'Sunday'];
        foreach ($fullNames as $num => $name) {
            $defaultSchedule[$num] = [
                'name' => $name,
                'enabled' => $num <= 4,
                'start' => '08:00',
                'end' => '17:00'
            ];
        }

        return $this->render('pages/account-centre/availability.html.twig', [
            'profile' => $profile,
            'availabilities' => $availabilities,
            'defaultSchedule' => $defaultSchedule
        ]);
    }

    #[Route('/availability/{id}/delete', name: 'availability_delete', methods: ['POST'])]
    public function deleteAvailability(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        AvailabilityRepository $availabilityRepository,
    ): Response {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn.');
            return $this->redirectToRoute('auth.login');
        }

        $csrfToken = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_availability' . $id, $csrfToken)) {
            $this->addFlash('error', 'Ongeldig veiligheidstoken.');
            return $this->redirectToRoute('account.availability');
        }

        $availability = $availabilityRepository->find($id);

        if (!$availability) {
            $this->addFlash('error', 'Beschikbaarheid niet gevonden.');
            return $this->redirectToRoute('account.availability');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        $profile = $account
            ? $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account])
            : null;

        if (!$profile || $availability->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze beschikbaarheid.');
        }

        try {
            $availability->softDelete();
            $entityManager->flush();

            $this->addFlash('success', 'Beschikbaarheid succesvol verwijderd.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Fout bij verwijderen: ' . $e->getMessage());
        }

        return $this->redirectToRoute('account.availability');
    }

    #[Route('/experience', name: 'experience', methods: ['GET', 'POST'])]
    public function experience(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        if (!$account) {
            $this->addFlash('error', 'Account niet gevonden.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile) {
            $this->addFlash('error', 'Profiel niet gevonden.');
            return $this->redirectToRoute('account.home');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_token');

            if (!$this->isCsrfTokenValid('save_experience', $csrfToken)) {
                $this->addFlash('error', 'Ongeldig veiligheidstoken. Probeer het opnieuw.');
                return $this->redirectToRoute('account.experience');
            }

            $jobTitle = trim((string)$request->request->get('job_title'));
            $organisationName = trim((string)$request->request->get('organisation_name'));
            $startDateStr = trim((string)$request->request->get('start_date'));
            $endDateStr = trim((string)$request->request->get('end_date'));
            $isCurrent = (bool)$request->request->get('is_current', false);
            $description = trim((string)$request->request->get('description'));

            if (empty($jobTitle) || empty($organisationName) || empty($startDateStr)) {
                $this->addFlash('error', 'Vul alstublieft alle verplichte velden in.');
            } else {
                try {
                    $experience = new ProfileExperience();

                    if (method_exists($experience, 'setId')) {
                        $experience->setId(Uuid::v4());
                    }

                    $experience->setJobTitle($jobTitle);
                    $experience->setOrganisationName($organisationName);
                    $experience->setStartDate(new \DateTimeImmutable($startDateStr));
                    $experience->setIsCurrent($isCurrent);

                    if ($isCurrent) {
                        $experience->setEndDate(null);
                    } else {
                        $experience->setEndDate(!empty($endDateStr) ? new \DateTimeImmutable($endDateStr) : null);
                    }

                    $experience->setDescription(!empty($description) ? $description : null);
                    $experience->setProfile($profile);

                    if (method_exists($experience, 'setCreatedAt')) {
                        $experience->setCreatedAt(new \DateTimeImmutable());
                    }
                    if (method_exists($experience, 'setLastModified')) {
                        $experience->setLastModified(new \DateTimeImmutable());
                    }

                    $entityManager->persist($experience);
                    $entityManager->flush();

                    $this->addFlash('success', 'Je werkervaring is succesvol toegevoegd!');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Fout bij het verwerken van de gegevens: ' . $e->getMessage());
                }
            }

            return $this->redirectToRoute('account.experience');
        }

        $experiences = $entityManager->getRepository(ProfileExperience::class)->findBy(
            ['profile' => $profile, 'deletedAt' => null],
            ['startDate' => 'DESC']
        );

        return $this->render('pages/account-centre/experience.html.twig', [
            'experiences' => $experiences,
        ]);
    }

    #[Route('/experience/{id}/delete', name: 'experience_delete', methods: ['POST'])]
    public function deleteExperience(string $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze actie uit te voeren.');
            return $this->redirectToRoute('auth.login');
        }

        $experience = $entityManager->getRepository(ProfileExperience::class)->find($id);

        if (!$experience) {
            $this->addFlash('error', 'Ervaring niet gevonden.');
            return $this->redirectToRoute('account.experience');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);

        if (!$profile || $experience->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze ervaring.');
        }

        $csrfToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_experience' . $experience->getId(), $csrfToken)) {

            if (method_exists($experience, 'softDelete')) {
                $experience->softDelete();
            } else {
                $experience->setDeletedAt(new \DateTimeImmutable());
            }

            $entityManager->flush();
            $this->addFlash('success', 'Werkervaring succesvol verwijderd.');
        } else {
            $this->addFlash('error', 'Ongeldig veiligheidstoken.');
        }

        return $this->redirectToRoute('account.experience');
    }

    #[Route('/modify', name: 'modify', methods: ['GET', 'POST'])]
    public function modify(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);

        if (!$account) {
            $this->addFlash('error', 'Account niet gevonden.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        $settings = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);

        if ($request->isMethod('POST')) {
            $username = trim((string)$request->request->get('username'));
            $email = trim((string)$request->request->get('email'));
            $bio = trim((string)$request->request->get('bio'));

            if (!empty($username)) {
                $account->setUsername($username);
            }
            if (!empty($email)) {
                $account->setEmail($email);
            }

            if ($profile) {
                $profile->setFirstName(trim((string) $request->request->get('first_name')));
                $profile->setLastName(trim((string) $request->request->get('last_name')));
                $profile->setDisplayName(trim((string) $request->request->get('display_name')) ?: null);
                $profile->setAvatarUrl(trim((string) $request->request->get('avatar_url')));
                $profile->setLocation(trim((string) $request->request->get('location')) ?: null);
                $profile->setDescription(!empty($bio) ? $bio : null);

                if (method_exists($profile, 'setLastModified')) {
                    $profile->setLastModified(new \DateTimeImmutable());
                }
            }

            if ($settings) {
                $settings->setLanguage(trim((string)$request->request->get('language', 'nl')));
                $settings->setTheme(trim((string)$request->request->get('theme', 'dark')));
                $settings->setProfileVisibility(trim((string)$request->request->get('profile_visibility', 'public')));
                $settings->setEmailNotificationsEnabled((bool)$request->request->get('email_notifications_enabled', false));

                if (method_exists($settings, 'setLastModified')) {
                    $settings->setLastModified(new \DateTimeImmutable());
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Je wijzigingen zijn succesvol opgeslagen!');

            return $this->redirectToRoute('account.modify');
        }

        return $this->render('pages/account-centre/modify.html.twig', [
            'account' => $account,
            'profile' => $profile,
            'settings' => $settings
        ]);
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);

        if (!$account) {
            $this->addFlash('error', 'Account niet gevonden.');
            return $this->redirectToRoute('auth.login');
        }

        $settings = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);

        if (!$settings) {
            $settings = new AccountSetting();
            $settings->setAccount($account);
            $settings->setLanguage('nl');
            $settings->setTheme('dark');
            $settings->setProfileVisibility('public');
            $settings->setEmailNotificationsEnabled(true);

            if (method_exists($settings, 'setId') && method_exists(Uuid::class, 'v4')) {
                $settings->setId(Uuid::v4());
            }
            if (method_exists($settings, 'setCreatedAt')) {
                $settings->setCreatedAt(new \DateTimeImmutable());
            }

            $entityManager->persist($settings);
        }

        if ($request->isMethod('POST')) {
            $notifyProjects = $request->request->has('notify_projects');
            $notifyNewsletter = $request->request->has('notify_newsletter');

            $settings->setEmailNotificationsEnabled($notifyProjects || $notifyNewsletter);

            if (method_exists($settings, 'setLastModified')) {
                $settings->setLastModified(new \DateTimeImmutable());
            }

            $entityManager->flush();

            $this->addFlash('success', 'Je notificatievoorkeuren zijn succesvol bijgewerkt!');
            return $this->redirectToRoute('account.notifications');
        }

        return $this->render('pages/account-centre/notifications.html.twig', [
            'settings' => $settings
        ]);
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(): Response
    {
        return $this->render('pages/account-centre/projects.html.twig');
    }

    #[Route('/reputation', name: 'reputation', methods: ['GET'])]
    public function reputation(): Response
    {
        return $this->render('pages/account-centre/reputation.html.twig');
    }

    #[Route('/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(): Response
    {
        return $this->render('pages/account-centre/reviews.html.twig');
    }

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('pages/account-centre/settings.html.twig');
    }

    #[Route('/signoff', name: 'signoff', methods: ['GET'])]
    public function signoff(): Response
    {
        return $this->render('pages/account-centre/signoff.html.twig');
    }

    #[Route('/skills', name: 'skills', methods: ['GET'])]
    public function skills(): Response
    {
        return $this->render('pages/account-centre/skills.html.twig');
    }
}