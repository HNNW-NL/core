<?php

namespace App\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\ProfileExperience;
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

    #[Route('/availability', name: 'availability', methods: ['GET'])]
    public function availability(): Response
    {
        return $this->render('pages/account-centre/availability.html.twig');
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

                    // OPGESCHOOND: Stuurt nu direct de boolean (true of false) naar de Entity
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
            $username = trim((string) $request->request->get('username'));
            $email = trim((string) $request->request->get('email'));
            $bio = trim((string) $request->request->get('bio'));

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

            if (method_exists($settings, 'setId') && method_exists(\Symfony\Component\Uid\Uuid::class, 'v4')) {
                $settings->setId(\Symfony\Component\Uid\Uuid::v4());
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
