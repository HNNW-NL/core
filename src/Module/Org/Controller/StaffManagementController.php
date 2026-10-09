<?php

namespace App\Module\Org\Controller;

use App\Module\Org\Service\StaffManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/org/staff', name: 'org.staff.manage.')]
final class StaffManagementController extends AbstractController
{
    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(
        Request $request,
        StaffManagementService $staff
    ): Response {
        if (!$this->isCsrfTokenValid(
            'staff_add',
            (string) $request->request->get('_token', '')
        )) {
            throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
        }

        try {
            $staff->addMember(
                (string) $request->request->get('email', ''),
                (string) $request->request->get('role_id', '')
            );

            $this->addFlash('success', 'Medewerker toegevoegd.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            throw $this->createAccessDeniedException($e->getMessage());
        }

        return $this->redirectToRoute('org.staff');
    }

    #[Route('/{id}/role', name: 'role', methods: ['POST'])]
    public function changeRole(
        string $id,
        Request $request,
        StaffManagementService $staff
    ): Response {
        if (!$this->isCsrfTokenValid(
            'staff_role_' . $id,
            (string) $request->request->get('_token', '')
        )) {
            throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
        }

        try {
            $staff->changeRole(
                $id,
                (string) $request->request->get('role_id', '')
            );

            $this->addFlash('success', 'Rol gewijzigd.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            throw $this->createAccessDeniedException($e->getMessage());
        }

        return $this->redirectToRoute('org.staff');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        string $id,
        Request $request,
        StaffManagementService $staff
    ): Response {
        if (!$this->isCsrfTokenValid(
            'staff_delete_' . $id,
            (string) $request->request->get('_token', '')
        )) {
            throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
        }

        try {
            $staff->removeMember($id);

            $this->addFlash('success', 'Medewerker verwijderd.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            throw $this->createAccessDeniedException($e->getMessage());
        }

        return $this->redirectToRoute('org.staff');
    }
}