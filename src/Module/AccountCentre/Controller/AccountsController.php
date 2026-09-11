<?php

namespace App\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\ProfileAvailability;
use App\Entity\Project\ProjectApplication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/account', name: 'account.')]
final class AccountsController extends AbstractController
{
    /**
     * Hulp-methode om het ingelogde account & profiel op te halen.
     */
    private function getAuthenticatedAccountAndProfile(Request $request, EntityManagerInterface $entityManager): array
    {
        $accountId = $request->getSession()->get('account_id');
        if (!$accountId) {
            return [null, null];
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        if (!$account) {
            return [null, null];
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);

        return [$account, $profile];
    }

    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/account-centre/index.html.twig');
    }

    #[Route('/applications', name: 'applications', methods: ['GET'])]
    public function applications(Request $request, EntityManagerInterface $entityManager): Response
    {
        [$account, $profile] = $this->getAuthenticatedAccountAndProfile($request, $entityManager);

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om je aanmeldingen te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        return $this->render('pages/account-centre/applications.html.twig', [
            'profile' => $profile
        ]);
    }

    #[Route('/applications/{id}/cancel', name: 'applications_cancel', methods: ['POST'])]
    public function applicationsCancel(ProjectApplication $application, Request $request, EntityManagerInterface $entityManager): Response
    {
        [$account, $profile] = $this->getAuthenticatedAccountAndProfile($request, $entityManager);

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze actie uit te voeren.');
            return $this->redirectToRoute('auth.login');
        }

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
    public function availability(Request $request, EntityManagerInterface $entityManager): Response
    {
        [$account, $profile] = $this->getAuthenticatedAccountAndProfile($request, $entityManager);

        if (!$account || !$profile) {
            $this->addFlash('error', 'Je moet ingelogd zijn om je beschikbaarheid te beheren.');
            return $this->redirectToRoute('auth.login');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('save_availability', $request->request->get('_token'))) {
                $this->addFlash('error', 'Ongeldig veiligheidstoken.');
                return $this->redirectToRoute('account.availability');
            }

            $daysActive = $request->request->all('days_active'); // Oorspronkelijke regel 27
            $startTimes = $request->request->all('start_time');
            $endTimes = $request->request->all('end_time');

            // Verwijder eerst de oude beschikbaarheid voor dit profiel
            $existingAvailabilities = $entityManager->getRepository(ProfileAvailability::class)->findBy(['profile' => $profile]);
            foreach ($existingAvailabilities as $oldAvailability) {
                $entityManager->remove($oldAvailability);
            }

            // Sla per geselecteerde dag de specifieke begin- en eindtijd op
            foreach ($daysActive as $dayOfWeek) {
                $dayOfWeek = (int) $dayOfWeek;
                $startTimeStr = $startTimes[$dayOfWeek] ?? '09:00';
                $endTimeStr = $endTimes[$dayOfWeek] ?? '17:00';

                $availability = new ProfileAvailability();
                if (method_exists($availability, 'setId')) {
                    $availability->setId(Uuid::v4());
                }

                $availability->setProfile($profile);
                $availability->setDayOfWeek($dayOfWeek);
                $availability->setStartTime(new \DateTimeImmutable($startTimeStr));
                $availability->setEndTime(new \DateTimeImmutable($endTimeStr));

                if (method_exists($availability, 'setCreatedAt')) {
                    $availability->setCreatedAt(new \DateTimeImmutable());
                }

                $entityManager->persist($availability);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Beschikbaarheid succesvol opgeslagen.');

            return $this->redirectToRoute('account.availability');
        }

        $availabilities = $entityManager->getRepository(ProfileAvailability::class)->findBy(['profile' => $profile]);

        return $this->render('pages/account-centre/availability.html.twig', [
            'availabilities' => $availabilities,
        ]);
    }

    #[Route('/experience', name: 'experience', methods: ['GET', 'POST'])]
    public function experience(Request $request, EntityManagerInterface $entityManager): Response
    {
        [$account, $profile] = $this->getAuthenticatedAccountAndProfile($request, $entityManager);

        if (!$account || !$profile) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('save_experience', $request->request->get('_token'))) {
                $this->addFlash('error', 'Ongeldig veiligheidstoken.');
                return $this->redirectToRoute('account.experience');
            }

            $jobTitle = trim((string) $request->request->get('job_title'));
            $organisationName = trim((string) $request->request->get('organisation_name'));
            $startDateStr = trim((string) $request->request->get('start_date'));
            $endDateStr = trim((string) $request->request->get('end_date'));
            $isCurrent = (bool) $request->request->get('is_current', false);
            $description = trim((string) $request->request->get('description'));

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
                    $experience->setEndDate(($isCurrent || empty($endDateStr)) ? null : new \DateTimeImmutable($endDateStr));
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

    #[Route('/modify', name: 'modify', methods: ['GET', 'POST'])]
    public function modify(Request $request, EntityManagerInterface $entityManager): Response
    {
        [$account, $profile] = $this->getAuthenticatedAccountAndProfile($request, $entityManager);

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $settings = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modify_account', $request->request->get('_token'))) {
                $this->addFlash('error', 'Ongeldig veiligheidstoken.');
                return $this->redirectToRoute('account.modify');
            }

            $username = trim((string) $request->request->get('username'));
            $email = trim((string) $request->request->get('email'));

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
                $profile->setDescription(trim((string) $request->request->get('bio')) ?: null);

                if (method_exists($profile, 'setLastModified')) {
                    $profile->setLastModified(new \DateTimeImmutable());
                }
            }

            if ($settings) {
                $settings->setLanguage(trim((string) $request->request->get('language', 'nl')));
                $settings->setTheme(trim((string) $request->request->get('theme', 'dark')));
                $settings->setProfileVisibility(trim((string) $request->request->get('profile_visibility', 'public')));
                $settings->setEmailNotificationsEnabled((bool) $request->request->get('email_notifications_enabled', false));

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

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(): Response { return $this->render('pages/account-centre/projects.html.twig'); }

    #[Route('/reputation', name: 'reputation', methods: ['GET'])]
    public function reputation(): Response { return $this->render('pages/account-centre/reputation.html.twig'); }

    #[Route('/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(): Response { return $this->render('pages/account-centre/reviews.html.twig'); }

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response { return $this->render('pages/account-centre/settings.html.twig'); }

    #[Route('/skills', name: 'skills', methods: ['GET'])]
    public function skills(): Response { return $this->render('pages/account-centre/skills.html.twig'); }
}