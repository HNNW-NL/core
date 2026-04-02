<?php

namespace App\Module\Communication\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/communication', name: 'communication.')]
final class CommunicationController extends AbstractController
{
    #[Route('/messages', name: 'messages', methods: ['GET'])]
    public function messages(): Response
    {
        return $this->render('communication/messages/index.html.twig');
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        return $this->render('communication/notifications/index.html.twig');
    }
}
