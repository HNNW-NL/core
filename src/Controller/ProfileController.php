<?php

namespace App\Controller;

use App\Entity\Account\Profile;
use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(SessionInterface $session): Response
    {
        $account = $this->getUser();
        $profile = $account?->getProfile();

        if (!$account) {
            $location = $session->get('guest_location', null);
        } else {
            $location = $profile?->getLocation();
        }

        return $this->render('pages/main/profile.html.twig', [
            'profile' => $profile,
            'guest_location' => $location ?? null
        ]);
    }

    #[Route('/profile/location', name: 'profile_update_location', methods: ['POST'])]
    public function updateLocation(
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): JsonResponse {

        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data['location'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Geen locatie ingevuld'
                ], 400);
            }

            $account = $this->getUser();

            if ($account) {
                // إذا كان المستخدم مسجلاً دخول
                $profile = $account->getProfile();

                if (!$profile) {
                    $profile = new Profile();
                    $profile->setAccount($account);
                    $profile->setFirstName('User');
                    $profile->setLastName('User');
                    $profile->setAvatarUrl('default.png');
                    $entityManager->persist($profile);
                }

                $profile->setLocation($data['location']);
                $entityManager->flush();

                return $this->json([
                    'success' => true,
                    'location' => $profile->getLocation()
                ]);

            } else {
                // ✅ زائر: ننشئ Account و Profile في قاعدة البيانات
                
                // 1. جلب Status من قاعدة البيانات
                $status = $entityManager->getRepository(\App\Entity\Common\Status::class)
                    ->find('8e46830a-38c4-4d46-9afb-7401619fd98c'); // استخدم ID الصحيح

                // 2. إنشاء Account جديد
                $guestAccount = new Account();
                $uuid = Uuid::v4()->toRfc4122();
                $guestAccount->setEmail('guest_' . $uuid . '@example.com');
                $guestAccount->setUsername('guest_' . substr($uuid, 0, 8));
                $guestAccount->setPasswordHash(password_hash($uuid, PASSWORD_DEFAULT));
                $guestAccount->setStatus($status); // ✅ تعيين Status

                $entityManager->persist($guestAccount);
                $entityManager->flush(); // حفظ Account

                // 3. إنشاء Profile مرتبط بالـ Account
                $guestProfile = new Profile();
                $guestProfile->setAccount($guestAccount);
                $guestProfile->setFirstName('Guest');
                $guestProfile->setLastName('User');
                $guestProfile->setAvatarUrl('default.png');
                $guestProfile->setLocation($data['location']);

                $entityManager->persist($guestProfile);
                $entityManager->flush(); // حفظ Profile

                // 4. تنظيف Session
                $session->remove('guest_location');

                return $this->json([
                    'success' => true,
                    'location' => $guestProfile->getLocation(),
                    'guest' => true
                ]);
            }

        } catch (\Exception $e) {
            error_log('Profile update error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'message' => 'Server fout: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/profile/test', name: 'profile_test')]
    public function test(): Response
    {
        dd($this->getUser());
    }

    #[Route('/profile/session-test', name: 'session_test')]
    public function sessionTest(SessionInterface $session): Response
    {
        $session->set('test_value', 'يشتغل!');
        $value = $session->get('test_value');
        dd($value);
    }
}