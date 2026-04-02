<?php

namespace App\Module\Member\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/members', name: 'member.')]
final class MemberController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('member/list/index.html.twig');
    }
}
