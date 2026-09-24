<?php

namespace App\Module\Admin\Controller;

use App\Entity\Account\Notification;
use App\Form\Admin\CreateNotificationType;
use App\Module\Admin\DTO\CreateNotificationDTO;
use App\Repository\Account\NotificationRepository;
use App\Repository\Account\ProfileRepository;
use App\Repository\Log\AuditLogRepository;
use App\Repository\Log\SystemLogRepository;
use App\Repository\Org\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
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
    public function logs(SystemLogRepository $systemLogRepository, AuditLogRepository $auditLogRepository): Response
    {
        $auditLogs = $auditLogRepository->findLatest(100);
        $systemLogs = $systemLogRepository->findLatest(100);

        
        return $this->render('pages/admin/logs.html.twig',
        array(
            'auditLogs' => $auditLogs,
            'systemLogs' => $systemLogs
        ));
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET','POST'])]
    public function notifications(Request $request, NotificationRepository $notificationRepository, EntityManagerInterface $entityManagerInterface,ObjectMapperInterface $objectMapper ): Response
    {
        $createNotificationDTO = new CreateNotificationDTO();
        $form = $this->createForm(CreateNotificationType::class, $createNotificationDTO);

        $notifications = $notificationRepository->findLatestChanged(10);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            
            $notifications = new Notification();

            $objectMapper->map($createNotificationDTO, $notifications);

            $entityManagerInterface->persist($notifications);

            $entityManagerInterface->flush();
            $this->addFlash('success','Notificatie toegevoegd');
            
            return $this->redirectToRoute('admin.notifications');
        }

        return $this->render('pages/admin/notifications.html.twig',
        array(
            'notifications' => $notifications,
            'form' => $form
        ));
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
