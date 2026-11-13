<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Repository\Log\SystemLogRepository;
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
            'accounts' => $em->getRepository(Account::class)->findBy([], ['createdAt' => 'DESC'], 10),
            'profiles' => $em->getRepository(Profile::class)->findBy([], ['createdAt' => 'DESC'], 10),
        ]);
    }

    #[Route('/accounts', name: 'accounts.home', methods: ['GET'])]
    #[IsGranted('ADMIN_MANAGE_ACCOUNTS')]
    public function accounts(Request $request, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $qb = $em->getRepository(Account::class)->createQueryBuilder('a');
        if ($search !== '') {
            $qb->andWhere('LOWER(a.username) LIKE :search OR LOWER(a.email) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }
        $accounts = $qb->orderBy('a.createdAt', 'DESC')->setMaxResults(100)->getQuery()->getResult();
        return $this->render('pages/admin/accounts/index.html.twig', [
            'accounts' => $accounts,
            'search' => $search,
        ]);
    }

    #[Route('/accounts/modify/{id}', name: 'accounts.modify', methods: ['GET'])]
    #[IsGranted('ADMIN_MANAGE_ACCOUNTS')]
    public function modifyAccount(string $id, EntityManagerInterface $em): Response
    {
        $account = $em->getRepository(Account::class)->find($id);
        if ($account === null) {
            throw $this->createNotFoundException('Account niet gevonden.');
        }
        return $this->render('pages/admin/accounts/modify.html.twig', [
            'id' => $id,
            'account' => $account,
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
    public function statuses(EntityManagerInterface $em): Response
    {
        return $this->render('pages/admin/statuses.html.twig', [
            'statuses' => $em->getRepository(Status::class)->findBy([], ['scope' => 'ASC', 'name' => 'ASC']),
        ]);
    }
}
