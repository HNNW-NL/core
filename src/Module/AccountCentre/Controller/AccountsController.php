<?php

namespace App\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Availability;
use App\Entity\Account\ProfileExperience;
use App\Entity\Project\ProjectApplication;
use App\Form\AccountCentre\ExperienceType;
use App\Form\AccountCentre\ModifyAccountType;
use App\Form\AccountCentre\NotificationSettingsType;
use App\Module\AccountCentre\DTO\ExperienceDTO;
use App\Module\AccountCentre\DTO\ModifyAccountDTO;
use App\Module\AccountCentre\DTO\NotificationSettingsDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account', name: 'account.')]
final class AccountsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/account-centre/index.html.twig');
    }

    #[Route('/applications', name: 'applications', methods: ['GET'])]
    public function applications(EntityManagerInterface $entityManager): Response
    {
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om je aanmeldingen te bekijken.');
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
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze actie uit te voeren.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile || $application->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze aanmelding.');
        }

        $csrfToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('cancel_application' . $application->getId()->toString(), $csrfToken)) {
            // de entity heeft zelf een softDelete methode, die zet deletedAt op nu
            $application->softDelete();

            $entityManager->flush();

            $this->addFlash('success', 'Je aanmelding is succesvol geannuleerd.');
        } else {
            $this->addFlash('error', 'Ongeldig veiligheidstoken. Probeer het opnieuw.');
        }

        return $this->redirectToRoute('account.applications');
    }

    #[Route('/availability', name: 'availability', methods: ['GET'])]
    public function availability(EntityManagerInterface $entityManager): Response
    {
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om je beschikbaarheid te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        if (!$profile) {
            $this->addFlash('error', 'Profiel niet gevonden.');
            return $this->redirectToRoute('account.home');
        }

        // alleen nog de lijst tonen via de entity, het opslaan komt uit pr #3 van team 4
        $availabilities = $entityManager->getRepository(Availability::class)->findBy(
            ['profile' => $profile, 'deletedAt' => null],
            ['validFrom' => 'DESC', 'createdAt' => 'ASC']
        );

        return $this->render('pages/account-centre/availability.html.twig', [
            'profile' => $profile,
            'availabilities' => $availabilities,
        ]);
    }

    #[Route('/availability/{id}/delete', name: 'availability_delete', methods: ['POST'])]
    public function deleteAvailability(string $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn.');
            return $this->redirectToRoute('auth.login');
        }

        $availability = $entityManager->getRepository(Availability::class)->find($id);

        if (!$availability) {
            $this->addFlash('error', 'Beschikbaarheid niet gevonden.');
            return $this->redirectToRoute('account.availability');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);

        if (!$profile || $availability->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze beschikbaarheid.');
        }

        $csrfToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_availability' . $id, $csrfToken)) {
            // net als bij werkervaring: de entity zet zelf deletedAt op nu
            $availability->softDelete();

            $entityManager->flush();
            $this->addFlash('success', 'Beschikbaarheid succesvol verwijderd.');
        } else {
            $this->addFlash('error', 'Ongeldig veiligheidstoken.');
        }

        return $this->redirectToRoute('account.availability');
    }

    #[Route('/experience', name: 'experience', methods: ['GET', 'POST'])]
    public function experience(Request $request, EntityManagerInterface $entityManager): Response
    {
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
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
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze actie uit te voeren.');
            return $this->redirectToRoute('auth.login');
        }

        $experience = $entityManager->getRepository(ProfileExperience::class)->find($id);

        if (!$experience) {
            $this->addFlash('error', 'Ervaring niet gevonden.');
            return $this->redirectToRoute('account.experience');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);

        if (!$profile || $experience->getProfile() !== $profile) {
            throw $this->createAccessDeniedException('Je bent niet de eigenaar van deze ervaring.');
        }

        $csrfToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_experience' . $experience->getId(), $csrfToken)) {
            // de entity heeft zelf een softDelete methode, die zet deletedAt op nu
            $experience->softDelete();

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
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
            return $this->redirectToRoute('auth.login');
        }

        $profile = $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account]);
        $settings = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);

        $modifyAccountDTO = new ModifyAccountDTO();
        $modifyAccountDTO->username = $account->getUsername();
        $modifyAccountDTO->email = $account->getEmail();
        $modifyAccountDTO->firstName = $profile?->getFirstName();
        $modifyAccountDTO->lastName = $profile?->getLastName();
        $modifyAccountDTO->displayName = $profile?->getDisplayName();
        $modifyAccountDTO->avatarUrl = $profile?->getAvatarUrl();
        $modifyAccountDTO->location = $profile?->getLocation();
        $modifyAccountDTO->description = $profile?->getDescription();
        $modifyAccountDTO->language = $settings?->getLanguage() ?? 'nl-NL';
        $modifyAccountDTO->theme = $settings?->getTheme() ?? 'dark';
        $modifyAccountDTO->profileVisibility = $settings?->getProfileVisibility() ?? 'private';

        $form = $this->createForm(ModifyAccountType::class, $modifyAccountDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // de kolom email is uniek in de database, dus als een ander account dit adres al heeft zou flush() een fout van 500 geven; daarom eerst zoeken en een nette fout op het veld zetten (door de fout is het formulier hieronder niet meer geldig)
            $anderAccount = $entityManager->getRepository(Account::class)->findOneBy(['email' => trim((string) $modifyAccountDTO->email)]);
            if ($anderAccount !== null && !$anderAccount->getId()->equals($account->getId())) {
                $form->get('email')->addError(new FormError('Dit e-mailadres is al in gebruik.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $account->setUsername(trim((string) $modifyAccountDTO->username));
            $account->setEmail(trim((string) $modifyAccountDTO->email));

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

            $displayName = trim((string) $modifyAccountDTO->displayName);
            $avatarUrl = trim((string) $modifyAccountDTO->avatarUrl);
            $location = trim((string) $modifyAccountDTO->location);
            $description = trim((string) $modifyAccountDTO->description);
            $language = trim((string) $modifyAccountDTO->language);

            $profile->setFirstName(trim((string) $modifyAccountDTO->firstName));
            $profile->setLastName(trim((string) $modifyAccountDTO->lastName));
            // een lege weergavenaam wordt null, dan valt de site terug op voor en achternaam
            $profile->setDisplayName($displayName !== '' ? $displayName : null);
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
        $account = $this->getAuthenticatedAccount();

        if (!$account) {
            $this->addFlash('error', 'Je moet ingelogd zijn om deze pagina te bekijken.');
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

        $form = $this->createForm(NotificationSettingsType::class, $notificationSettingsDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->setEmailNotificationsEnabled($notificationSettingsDTO->emailNotificationsEnabled);

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

    private function getAuthenticatedAccount(): ?Account
    {
        $user = $this->getUser();

        return $user instanceof Account ? $user : null;
    }
}
