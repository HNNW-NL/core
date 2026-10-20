<?php

namespace App\Module\Admin\Controller;

use App\Entity\Account\Notification;
use App\Entity\Log\AuditLog;
use App\Entity\Log\SystemLog;
use App\Form\Admin\AuditLogSearchType;
use App\Form\Admin\CreateNotificationType;
use App\Form\Admin\SystemLogSearchType;
use App\Module\Admin\DTO\AuditLogSearchDisplayDTO;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use App\Module\Admin\DTO\CreateNotificationDTO;
use App\Module\Admin\DTO\SystemLogSearchDisplayDTO;
use App\Module\Admin\DTO\SystemLogSearchDTO;
use App\Repository\Account\NotificationRepository;
use App\Repository\Org\AccountRepository;
use App\Repository\Org\OrganisatieRepository;
use App\Repository\Project\ProjectRepository;
use App\Twig\Components\Shared\Search\SearchDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin.')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(AccountRepository $accountRepository,
     OrganisatieRepository $organisatieRepository, ProjectRepository $projectRepository): Response
    {
        $activeAccounts = $accountRepository->countActive();
        $activeOrganizations = $organisatieRepository->countActive();
        $activeProjects = $projectRepository->Count();
        
        return $this->render('pages/admin/index.html.twig',
        array(
            'activeAccounts' => $activeAccounts,
            'activeOrganizations' =>  $activeOrganizations,
            'activeProjects' =>  $activeProjects
        ));
    }

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function logs(): Response
    {
        $auditLogSearch = new SearchDTO();
        $auditLogSearch->dto = AuditLogSearchDTO::class;
        $auditLogSearch->displayDTO = AuditLogSearchDisplayDTO::class;
        $auditLogSearch->entity = AuditLog::class;
        $auditLogSearch->form = AuditLogSearchType::class;
        $auditLogSearch->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];

        $systemLogSearch = new SearchDTO();
        $systemLogSearch->dto = SystemLogSearchDTO::class;
        $systemLogSearch->displayDTO = SystemLogSearchDisplayDTO::class;
        $systemLogSearch->entity = SystemLog::class;
        $systemLogSearch->form = SystemLogSearchType::class;
        $systemLogSearch->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        
        return $this->render('pages/admin/logs.html.twig',
        array(
            'auditLogSearch' => $auditLogSearch,
            'systemLogSearch' => $systemLogSearch
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
