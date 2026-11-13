<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Entity\Common\Status;
use App\Repository\Log\SystemLogRepository;
use App\Repository\Common\StatusRepository;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\Uid\Ulid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin.')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    #[IsGranted('ADMIN_VIEW_DASHBOARD')]
    public function index(EntityManagerInterface $em, SystemLogRepository $logs): Response
    {
        return $this->render('pages/admin/index.html.twig', [
            'logs' => $logs->findLatest(10),
        ]);
    }

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    #[IsGranted('ADMIN_VIEW_LOGS')]
    public function logs(): Response
    {
        return $this->render('pages/admin/logs.html.twig');
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    #[IsGranted('ADMIN_VIEW_DASHBOARD')]
    public function notifications(): Response
    {
        return $this->render('pages/admin/notifications.html.twig');
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
    public function statuses(StatusRepository $repository, ProjectParticipantUpdateRoleHandler $workflow): Response
    {
        $statuses = $repository->findBy([], ['scope' => 'ASC', 'name' => 'ASC']);
        return $this->render('pages/admin/statuses.html.twig', [
            'statuses' => $statuses,
            'labels' => array_map($workflow->applicationStatusLabel(...), $statuses),
            'transitions' => array_map($workflow->configuredTransitions(...), $statuses),
            'editableTransitions' => array_map($workflow->isPendingStatus(...), $statuses),
        ]);
    }

    #[Route('/statuses/{id}/transitions', name: 'statuses.transitions', methods: ['POST'])]
    #[IsGranted('ADMIN_MANAGE_SETTINGS')]
    public function saveTransitions(string $id, Request $request, StatusRepository $repository, EntityManagerInterface $em, ProjectParticipantUpdateRoleHandler $workflow): Response
    {
        if (!$this->isCsrfTokenValid('status-transitions-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $status = Ulid::isValid($id) ? $repository->find(Ulid::fromString($id)) : null;
        if (!$status instanceof Status || !$workflow->isPendingStatus($status)) {
            throw $this->createNotFoundException();
        }
        $status->configureApplicationTransitions($request->request->getBoolean('accept'), $request->request->getBoolean('reject'));
        $em->flush();
        return $this->redirectToRoute('admin.statuses', status: 303);
    }

    #[Route('/statuses/save', name: 'statuses.save', methods: ['POST'])]
    #[IsGranted('ADMIN_MANAGE_SETTINGS')]
    public function saveStatus(Request $request, StatusRepository $repository, EntityManagerInterface $em): Response
    {
        $id = $request->request->getString('id');
        if (!$this->isCsrfTokenValid('status-save-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $colour = $request->request->getString('colour');
        if (!preg_match('/\A#[a-f0-9]{6}\z/i', $colour)) {
            $this->addFlash('error', 'Ongeldige kleurcode.');
            return $this->redirectToRoute('admin.statuses', status: 303);
        }
        if ($id === '') {
            $name = trim($request->request->getString('name'));
            $scope = $request->request->getString('scope');
            if (!preg_match('/\Acustom_[a-z0-9_]{1,60}\z/', $name)
                || $repository->findOneBy(['scope' => $scope]) === null
                || $repository->findOneByScopeAndName($scope, $name) !== null) {
                $this->addFlash('error', 'Gebruik een unieke statuscode die begint met custom_ en kies een bestaand object.');
                return $this->redirectToRoute('admin.statuses', status: 303);
            }
            $status = (new Status())->setName($name)->setScope($scope);
            $em->persist($status);
        } else {
            $status = Ulid::isValid($id) ? $repository->find(Ulid::fromString($id)) : null;
            if (!$status instanceof Status) {
                throw $this->createNotFoundException();
            }
        }
        $status->setColourHex($colour);
        $em->flush();
        return $this->redirectToRoute('admin.statuses', status: 303);
    }

    #[Route('/statuses/{id}/delete', name: 'statuses.delete', methods: ['POST'])]
    #[IsGranted('ADMIN_MANAGE_SETTINGS')]
    public function deleteStatus(string $id, Request $request, StatusRepository $repository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('status-delete-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $status = Ulid::isValid($id) ? $repository->find(Ulid::fromString($id)) : null;
        if (!$status instanceof Status) {
            throw $this->createNotFoundException();
        }
        if (!str_starts_with((string) $status->getName(), 'custom_')) {
            throw $this->createAccessDeniedException();
        }
        try {
            $em->remove($status);
            $em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'Deze status is in gebruik en kan niet worden verwijderd.');
        }
        return $this->redirectToRoute('admin.statuses', status: 303);
    }
}
