<?php

namespace App\Module\Social\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/social', name: 'social.')]
final class SocialController extends AbstractController
{
    #[Route('/forum', name: 'forum', methods: ['GET'])]
    public function forum(): Response
    {
        return $this->render('social/forum/index.html.twig');
    }

    #[Route('/groups', name: 'groups', methods: ['GET'])]
    public function groups(): Response
    {
        return $this->render('social/groups/index.html.twig');
    }

    #[Route('/polls', name: 'polls', methods: ['GET'])]
    public function polls(): Response
    {
        return $this->render('social/polls/index.html.twig');
    }
}
