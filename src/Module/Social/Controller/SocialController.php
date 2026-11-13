<?php

namespace App\Module\Social\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Social page routes.
 *
 * Post and category entities do not yet exist. Until they are introduced,
 * these actions render the existing placeholder templates without pretending
 * that a record has been loaded or persisted.
 */
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

    #[Route('/categories/{slug}', name: 'viewCategory', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function viewCategory(string $slug): Response
    {
        return $this->render('pages/social/categories-view.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/create-post', name: 'createPost', methods: ['GET'])]
    public function createPost(): Response
    {
        if ($this->getUser() === null) {
            throw $this->createAccessDeniedException('Log in om een bericht aan te maken.');
        }

        return $this->render('pages/social/posts/create.html.twig');
    }

    #[Route('/p/{slug}', name: 'viewPost', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function viewPost(string $slug): Response
    {
        return $this->render('pages/social/posts/view.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/posts/{id}/modify', name: 'modifyPost', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function modifyPost(string $id): Response
    {
        if ($this->getUser() === null) {
            throw $this->createAccessDeniedException('Log in om een bericht te wijzigen.');
        }

        // Ownership checks become mandatory once a Post entity exists.
        return $this->render('pages/social/posts/modify.html.twig', [
            'id' => $id,
        ]);
    }
}
