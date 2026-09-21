<?php

namespace App\Module\Admin\Controller;

use App\Repository\Account\ProfileRepository;
use App\Repository\Log\AuditLogRepository;
use App\Repository\Log\SystemLogRepository;
use App\Repository\Org\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin.')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(SystemLogRepository $systemLogRepository,
     AccountRepository $accountRepository, ProfileRepository $profileRepository): Response
    {
        $logs = $systemLogRepository->findLatest(10);
        $accounts = $accountRepository->findLatestChanged(10);
        $profiles = $profileRepository->findLatestChanged(10);
        
        return $this->render('pages/admin/index.html.twig',
        array(
            'logs' => $logs,
            'accounts' =>  $accounts,
            'profiles' =>  $profiles
        ));
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
