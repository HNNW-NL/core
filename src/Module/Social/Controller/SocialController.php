<?php

namespace App\Module\Social\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/social', name: 'social.')]
final class SocialController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/social/index.html.twig');
    }

    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function categories(): Response
    {
        return $this->render('pages/social/categories.html.twig');
    }

    #[Route('/categories/{id}', name: 'viewCategory', methods: ['GET'])]
    public function viewCategory(string $id): Response
    {
        return $this->render('pages/social/categories-view.html.twig', [
            'id' => $id
        ]);
    }

    #[Route('/create-post', name: 'createPost', methods: ['GET'])]
    public function createPost(): Response
    {
        return $this->render('pages/social/posts/create.html.twig');
    }

    #[Route('/p/{id}', name: 'viewPost', methods: ['GET'])]
    public function viewPost(string $id): Response
    {
        return $this->render('pages/social/posts/view.html.twig', [
            'id' => $id
        ]);
    }

    #[Route('/p/{id}/modify', name: 'modifyPost', methods: ['GET'])]
    public function modifyPost(string $id): Response
    {
        return $this->render('pages/social/posts/modify.html.twig', [
            'id' => $id
        ]);
    }
}
