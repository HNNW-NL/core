<?php

namespace App\Module\Auth\EventListener;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
final class LoginSuccessListener
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        // als het goed is stuurt symfony dit event na een gelukte form_login, dan zetten wij hier de laatste-login-datum
        $account = $event->getUser();

        if (!$account instanceof Account) {
            return;
        }

        $account->login();
        $this->em->flush();
    }
}
