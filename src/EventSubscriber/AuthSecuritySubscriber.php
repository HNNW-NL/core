<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Exception as DatabaseException;
use Doctrine\ORM\Exception\ORMException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Environment;

final class AuthSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Environment $twig, private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['secureResponse', -16], KernelEvents::EXCEPTION => ['handleUnavailableService', 64]];
    }

    public function secureResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $path = $event->getRequest()->getPathInfo();
        if (!preg_match('~^/(?:auth|account)(?:/|$)~', $path)) {
            return;
        }
        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'no-store, private');
        $headers->set('X-Content-Type-Options', 'nosniff');
        if (preg_match('~^/auth/(?:reset-password|verify-email)/~', $path)) {
            $headers->set('Referrer-Policy', 'no-referrer');
            $headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        }
    }

    public function handleUnavailableService(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        $exception = $event->getThrowable();
        if (!preg_match('~^/(?:auth|account)(?:/|$)~', $path)
            || (!$exception instanceof DatabaseException && !$exception instanceof ORMException && !$exception instanceof TransportExceptionInterface)) {
            return;
        }
        $this->logger->error('Account service is temporarily unavailable.', ['exception_class' => $exception::class]);
        $event->setResponse(new Response($this->twig->render('pages/auth/unavailable.html.twig', [
            'sensitive_auth_page' => true,
        ]), Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '60']));
    }
}
