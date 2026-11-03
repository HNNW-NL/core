<?php

namespace App\Module\Admin\Controller;

use App\Entity\Account\Notification;
use App\Form\Admin\CreateNotificationType;
use App\Module\Admin\DTO\CreateNotificationDTO;
use App\Repository\Account\NotificationRepository;
use App\Repository\Org\AccountRepository;
use App\Repository\Org\OrganisatieRepository;
use App\Repository\Project\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Class-level: /admin/* vereist sowieso ROLE_ADMIN (zie ook security.yaml
// access_control). Daarnaast checken losse routes hieronder een specifieke
// Permission, zodat niet elke admin per se overal bij mag.
#[Route('/admin', name: 'admin.')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    #[IsGranted('ADMIN_VIEW_DASHBOARD')]
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
    #[IsGranted('ADMIN_VIEW_LOGS')]
    public function logs(): Response
    {
        return $this->render('pages/admin/logs.html.twig');
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
    #[IsGranted('ADMIN_MANAGE_SETTINGS')]
    public function settings(): Response
    {
        return $this->render('pages/admin/settings.html.twig');
    }

    #[Route('/social/manage-staff', name: 'social.manageStaff', methods: ['GET'])]
    #[IsGranted('ADMIN_MANAGE_STAFF')]
    public function socialManageStaff(): Response
    {
        return $this->render('pages/admin/social-staff.html.twig');
    }

    #[Route('/statuses', name: 'statuses', methods: ['GET'])]
    #[IsGranted('ADMIN_MANAGE_SETTINGS')]
    public function statuses(): Response
    {
        return $this->render('pages/admin/statuses.html.twig');
    }
}
