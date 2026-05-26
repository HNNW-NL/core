<?php

namespace App\Module\Main\Controller;

use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'main.')]
final class ProfileController extends AbstractController  
{
    #[Route('/profile/update-location', name: 'profile_update_location', methods: ['POST'])]
    public function updateLocation(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Niet ingelogd'], 401);
        }
        
        $profile = $user->getProfile();
        
        if (!$profile) {
            return $this->json(['error' => 'Profiel niet gevonden'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        $location = $data['location'] ?? '';
        
        $profile->setLocation($location);
        $profile->setLastModified(new \DateTime());
        
        $entityManager->flush();
        
        return $this->json([
            'success' => true, 
            'location' => $location
        ]);
    }
}