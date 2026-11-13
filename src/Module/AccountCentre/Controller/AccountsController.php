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
use App\Repository\Project\ProjectRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;
use function PHPUnit\Framework\throwException;

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
        $account = $this->getAndCheckAccount();

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
        $account = $this->getAndCheckAccount();

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile || $application->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze aanmelding.');
        }

        $csrfToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('cancel_application' . $application->getId()->toString(), $csrfToken)) {

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
        $account = $this->getAndCheckAccount();

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile) {
            $this->addFlash('error', 'Profiel niet gevonden.');
            return $this->redirectToRoute('account.home');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_token');

            $daysInput = $request->request->all('days_config');

            $activeDays = [];
            $startTime = '08:00';
            $endTime = '17:00';
            $totalHours = 0;

            foreach (range(0, 6) as $dayNum) {
                $dayConfig = $daysInput[$dayNum] ?? [];

                if (empty($dayConfig) && isset($daysInput[$dayNum + 1])) {
                    $dayConfig = $daysInput[$dayNum + 1];
                }

                if (isset($dayConfig['enabled'])) {
                    $activeDays[] = $dayNum;

                    $start = $dayConfig['start'] ?? '08:00';
                    $end = $dayConfig['end'] ?? '17:00';

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

                foreach ($activeDays as $dayNumber => $dayOfWeek) {
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

        $availabilities = $availabilityRepository->findActiveForProfile($profile);

        return $this->render('pages/account-centre/availability.html.twig', [
            'profile' => $profile,
            'availabilities' => $availabilities,
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
        $account = $this->getAndCheckAccount();

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile) {
            $this->addFlash('error', 'Profiel niet gevonden.');
            return $this->redirectToRoute('account.home');
        }

        if ($request->isMethod('POST')) {
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
        $account = $this->getAndCheckAccount();

        $experience = $entityManager->getRepository(ProfileExperience::class)->find($id);

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
        $account = $this->getAndCheckAccount();
        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account, 'deletedAt' => null]);
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

            $entityManager->flush();

            $this->addFlash('success', 'Je wijzigingen zijn succesvol opgeslagen!');

            return $this->redirectToRoute('account.modify');
        }

        return $this->render('pages/account-centre/modify.html.twig', [
            'account' => $account,
            'profile' => $profile
        ]);
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request, EntityManagerInterface $entityManager): Response
    {
        $account = $this->getAndCheckAccount();

        $settings = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);

        if (!$settings) {
            $settings = $this->createAccountSettings($entityManager, $account);
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
    public function projects(ProjectRepository $projectRepository, EntityManagerInterface $entityManager): Response
    {
        $account = $this->getAndCheckAccount();
        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        $projects = $projectRepository->findByProfile($profile);


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

    #[Route('/settings', name: 'settings', methods: ['GET','POST'])]
    public function settings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $account = $this->getAndCheckAccount();
        $settings = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);

        if (!$settings) {
            $settings = $this->createAccountSettings($entityManager, $account);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_settings', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Ongeldige CSRF token');
            }
            if ($settings) {
                $settings->setLanguage(trim((string)$request->request->get('language', 'nl')));
                $settings->setTheme(trim((string)$request->request->get('theme', 'dark')));
                $settings->setProfileVisibility(trim((string)$request->request->get('profile_visibility', 'public')));
                $settings->setEmailNotificationsEnabled((bool)$request->request->get('email_notifications_enabled', false));

                $entityManager->flush();
                $this->addFlash('success', 'Je instellingen zijn succesvol opgeslagen!');
                return $this->redirectToRoute('account.settings');
            }
        }

        return $this->render('pages/account-centre/settings.html.twig', [
            'account' => $account,
            'settings' => $settings
        ]);
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

        if (method_exists($settings, 'setId') && method_exists(\Symfony\Component\Uid\Uuid::class, 'v4')) {
            $settings->setId(\Symfony\Component\Uid\Uuid::v4());
        }
        if (method_exists($settings, 'setCreatedAt')) {
            $settings->setCreatedAt(new \DateTimeImmutable());
        }

        $entityManager->persist($settings);

        return $settings;
    }
}
