<?php

namespace App\Module\Admin\Service;

use App\Entity\Log\SystemLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SystemLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {}

    public function log(
        int $code,
        string $level,
        string $message,
        ?array $context = null
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $systemLog = new SystemLog();
        $systemLog->setCode($code);
        $systemLog->setLevel($level);
        $systemLog->setMessage($message);
        $systemLog->setRoute($request?->getPathInfo());
        $systemLog->setMethod($request?->getMethod());
        $systemLog->setUserAgent($request?->headers->get('User-Agent'));
        $systemLog->setContextJson($context ?? []);
        $systemLog->setRequestIp($request?->getClientIp());

        $this->entityManager->persist($systemLog);
        $this->entityManager->flush();
    }
}