<?php

namespace App\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\ProfileExperience;
use App\Entity\Project\ProjectApplication;
use App\Form\AccountCentre\AvailabilityType;
use App\Form\AccountCentre\ExperienceType;
use App\Form\AccountCentre\ModifyAccountType;
use App\Form\AccountCentre\NotificationSettingsType;
use App\Module\AccountCentre\DTO\AvailabilityDTO;
use App\Module\AccountCentre\DTO\ExperienceDTO;
use App\Module\AccountCentre\DTO\ModifyAccountDTO;
use App\Module\AccountCentre\DTO\NotificationSettingsDTO;
use Doctrine\DBAL\Connection;
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

        return $this->render('pages/account-centre/applications.html.twig', [
            'profile' => $profile
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

            if (method_exists($application, 'softDelete')) {
                $application->softDelete();
            } else {
                $application->setDeletedAt(new \DateTimeImmutable());
            }

            $entityManager->flush();

            $this->addFlash('success', 'Je aanmelding is succesvol geannuleerd.');
        } else {
            $this->addFlash('error', 'Ongeldig veiligheidstoken. Probeer het opnieuw.');
        }

        return $this->redirectToRoute('account.applications');
    }

    #[Route('/availability', name: 'availability', methods: ['GET', 'POST'])]
    public function availability(Request $request, EntityManagerInterface $entityManager, Connection $connection): Response
    {
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

        $availabilityDTO = new AvailabilityDTO();
        $form = $this->createForm(AvailabilityType::class, $availabilityDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activeDays = [];
            $startTime = '08:00';
            $endTime = '17:00';
            $totalHours = 0;

            foreach (range(0, 6) as $dayNum) {
                $dayConfig = $availabilityDTO->daysConfig[$dayNum] ?? null;

                if (!$dayConfig || !$dayConfig->enabled) {
                    continue;
                }

                $activeDays[] = $dayNum;

                $start = substr((string) ($dayConfig->start ?? '08:00'), 0, 5);
                $end = substr((string) ($dayConfig->end ?? '17:00'), 0, 5);

                $startTime = $start;
                $endTime = $end;

                try {
                    $timeStart = new \DateTime($start);
                    $timeEnd = new \DateTime($end);
                    if ($timeEnd > $timeStart) {
                        $diff = $timeStart->diff($timeEnd);
                        $totalHours += $diff->h + ($diff->i / 60);
                    } else {
                        $totalHours += 8;
                    }
                } catch (\Exception $e) {
                    $totalHours += 8;
                }
            }

            $hours = (int) round($totalHours ?: ($availabilityDTO->hoursPerWeek ?? 0));
            $typeCode = ($hours >= 32) ? 'FT' : 'PT';

            $compactType = implode('', $activeDays) . '|' . $startTime . '-' . $endTime . '|' . $typeCode;
            $compactType = substr($compactType, 0, 25);
            $startDate = ($availabilityDTO->startDate ?? new \DateTimeImmutable())->format('Y-m-d');

            try {
                $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

                $connection->insert('availabilities', [
                    'id' => Uuid::v4()->toString(),
                    'availability_type' => $compactType,
                    'hours_per_week' => $hours,
                    'start_date' => $startDate,
                    'end_date' => null,
                    'profile_id' => $profile->getId()->toString(),
                    'created_at' => $now,
                    'last_modified' => $now,
                    'deleted_at' => null
                ]);

                $this->addFlash('success', 'Beschikbaarheidsschema succesvol opgeslagen!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Databasefout bij opslaan: ' . $e->getMessage());
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
            'defaultSchedule' => $defaultSchedule,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/availability/{id}/delete', name: 'availability_delete', methods: ['POST'])]
    public function deleteAvailability(string $id, Request $request, Connection $connection): Response
    {
        $session = $request->getSession();
        if (!$session->get('account_id')) {
            $this->addFlash('error', 'Je moet ingelogd zijn.');
            return $this->redirectToRoute('auth.login');
        }

        $csrfToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_availability' . $id, $csrfToken)) {
            try {
                $connection->update(
                    'availabilities',
                    ['deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
                    ['id' => $id]
                );
                $this->addFlash('success', 'Beschikbaarheid succesvol verwijderd (soft delete).');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Fout bij verwijderen: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Ongeldig veiligheidstoken.');
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

        $experienceDTO = new ExperienceDTO();
        $form = $this->createForm(ExperienceType::class, $experienceDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $experience = new ProfileExperience();
                $description = trim((string) $experienceDTO->description);

                $experience->setJobTitle(trim((string) $experienceDTO->jobTitle));
                $experience->setOrganisationName(trim((string) $experienceDTO->organisationName));
                $experience->setStartDate($experienceDTO->startDate ?? new \DateTimeImmutable());
                $experience->setIsCurrent($experienceDTO->isCurrent);
                $experience->setEndDate($experienceDTO->isCurrent ? null : $experienceDTO->endDate);
                $experience->setDescription($description !== '' ? $description : null);
                $experience->setProfile($profile);

                $entityManager->persist($experience);
                $entityManager->flush();

                $this->addFlash('success', 'Je werkervaring is succesvol toegevoegd!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Fout bij het verwerken van de gegevens: ' . $e->getMessage());
            }

            return $this->redirectToRoute('account.experience');
        }

        $experiences = $entityManager->getRepository(ProfileExperience::class)->findBy(
            ['profile' => $profile, 'deletedAt' => null],
            ['startDate' => 'DESC']
        );

        return $this->render('pages/account-centre/experience.html.twig', [
            'experiences' => $experiences,
            'form' => $form->createView(),
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

        $modifyAccountDTO = new ModifyAccountDTO();
        $modifyAccountDTO->username = $account->getUsername();
        $modifyAccountDTO->firstName = $profile?->getFirstName();
        $modifyAccountDTO->lastName = $profile?->getLastName();
        $modifyAccountDTO->avatarUrl = $profile?->getAvatarUrl();
        $modifyAccountDTO->location = $profile?->getLocation();
        $modifyAccountDTO->description = $profile?->getDescription();
        $modifyAccountDTO->language = $settings?->getLanguage() ?? 'nl-NL';
        $modifyAccountDTO->theme = $settings?->getTheme() ?? 'dark';
        $modifyAccountDTO->profileVisibility = $settings?->getProfileVisibility() ?? 'private';

        $form = $this->createForm(ModifyAccountType::class, $modifyAccountDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $account->setUsername(trim((string) $modifyAccountDTO->username));

            if (!$profile) {
                $profile = new Profile();
                $profile->setAccount($account);
                $entityManager->persist($profile);
            }

            if (!$settings) {
                $settings = new AccountSetting();
                $settings->setAccount($account);
                $entityManager->persist($settings);
            }

            $avatarUrl = trim((string) $modifyAccountDTO->avatarUrl);
            $location = trim((string) $modifyAccountDTO->location);
            $description = trim((string) $modifyAccountDTO->description);
            $language = trim((string) $modifyAccountDTO->language);

            $profile->setFirstName(trim((string) $modifyAccountDTO->firstName));
            $profile->setLastName(trim((string) $modifyAccountDTO->lastName));
            $profile->setAvatarUrl($avatarUrl);
            $profile->setLocation($location !== '' ? $location : null);
            $profile->setDescription($description !== '' ? $description : null);

            $settings->setLanguage($language !== '' ? $language : 'nl-NL');
            $settings->setTheme($modifyAccountDTO->theme ?? 'dark');
            $settings->setProfileVisibility($modifyAccountDTO->profileVisibility ?? 'private');

            $entityManager->flush();

            $this->addFlash('success', 'Je wijzigingen zijn succesvol opgeslagen!');

            return $this->redirectToRoute('account.modify');
        }

        return $this->render('pages/account-centre/modify.html.twig', [
            'account' => $account,
            'profile' => $profile,
            'settings' => $settings,
            'form' => $form->createView(),
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
            $settings->setLanguage('nl-NL');
            $settings->setTheme('dark');
            $settings->setProfileVisibility('public');
            $settings->setEmailNotificationsEnabled(true);

            $entityManager->persist($settings);
        }

        $notificationSettingsDTO = new NotificationSettingsDTO();
        $notificationSettingsDTO->emailNotificationsEnabled = $settings->isEmailNotificationsEnabled();
        $notificationSettingsDTO->newsletterEnabled = $settings->isEmailNotificationsEnabled();

        $form = $this->createForm(NotificationSettingsType::class, $notificationSettingsDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->setEmailNotificationsEnabled(
                $notificationSettingsDTO->emailNotificationsEnabled || $notificationSettingsDTO->newsletterEnabled
            );

            $entityManager->flush();

            $this->addFlash('success', 'Je notificatievoorkeuren zijn succesvol bijgewerkt!');
            return $this->redirectToRoute('account.notifications');
        }

        return $this->render('pages/account-centre/notifications.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
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
