<?php

namespace App\Module\Admin\Controller;

use App\Module\Admin\DTO\StatusDTO;
use App\Module\Admin\Handler\CreateStatusHandler;
use App\Module\Admin\Service\StatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin.')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/admin/index.html.twig');
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
    public function statuses(
        StatusService $statusService
    ): Response {
        return $this->render('pages/admin/statuses.html.twig', [
            'statuses' => $statusService->getAllStatuses(),
        ]);
    }

    #[Route('/statuses/create', name: 'statuses.create', methods: ['GET', 'POST'])]
    public function createStatus(
        Request $request,
        CreateStatusHandler $handler
    ): Response {
        if ($request->isMethod('POST')) {

            $dto = new StatusDTO(
                $request->request->get('name'),
                $request->request->get('colourHex'),
                $request->request->get('scope')
            );

            $handler->handle($dto);

            return $this->redirectToRoute('admin.statuses');
        }

        return $this->render('pages/admin/statuses-create.html.twig');
    }
}