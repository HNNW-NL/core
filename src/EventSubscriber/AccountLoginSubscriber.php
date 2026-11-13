<?php

namespace App\EventSubscriber;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class AccountLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Account) {
            return;
        }

        $user->login();
        $this->entityManager->flush();
    }
}
