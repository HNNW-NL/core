<?php

namespace App\Module\Admin\Service;

use App\Entity\Account\Account;
use App\Entity\Log\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {}

    public function log(
        ?Account $actorAccount,
        string $actorUsername,
        string $actorEmail,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $auditLog = new AuditLog();
        $auditLog->setActorAccount($actorAccount);
        $auditLog->setActorUsername($actorUsername);
        $auditLog->setActorEmail($actorEmail);
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setOldValuesJson($oldValues ?? []);
        $auditLog->setNewValuesJson($newValues ?? []);
        $auditLog->setRequestIp($request?->getClientIp() ?? 'unknown');
        $auditLog->setUserAgent($request?->headers->get('User-Agent') ?? 'unknown');

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }
}