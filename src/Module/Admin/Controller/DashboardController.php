<?php

namespace App\Module\Admin\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Log\SystemLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin.')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('pages/admin/index.html.twig', [
            'logs' => $entityManager->getRepository(SystemLog::class)->findBy([], ['createdAt' => 'DESC'], 10),
            'accounts' => array_map(static fn (Account $account): array => [
                'name' => $account->getUsername(),
                'updatedAt' => $account->getLastModified(),
            ], $entityManager->getRepository(Account::class)->findBy(['deletedAt' => null], ['lastModified' => 'DESC'], 10)),
            'profiles' => array_map(static fn (Profile $profile): array => [
                'fullName' => $profile->getDisplayName() ?: trim($profile->getFirstName() . ' ' . $profile->getLastName()),
                'updatedAt' => $profile->getLastModified(),
            ], $entityManager->getRepository(Profile::class)->findBy(['deletedAt' => null], ['lastModified' => 'DESC'], 10)),
        ]);
    }

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function logs(): Response
    {
        return $this->render('pages/admin/logs.html.twig');
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        return $this->render('pages/admin/notifications.html.twig');
    }

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('pages/admin/settings.html.twig');
    }

    #[Route('/social/manage-staff', name: 'social.manageStaff', methods: ['GET'])]
    public function socialManageStaff(): Response
    {
        return $this->render('pages/admin/social-staff.html.twig');
    }

    #[Route('/statuses', name: 'statuses', methods: ['GET'])]
    public function statuses(): Response
    {
        return $this->render('pages/admin/statuses.html.twig');
    }
}
