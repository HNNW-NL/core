<?php

namespace App\Module\Admin\Controller;

use App\Repository\Log\AuditLogRepository;
use App\Repository\Log\SystemLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api/logs', name: 'admin.api.logs.')]
final class AdminLogsApiController extends AbstractController
{
    #[Route('/system', name: 'system', methods: ['GET'])]
    public function system(SystemLogRepository $systemLogRepository): JsonResponse
    {
        $logs = $systemLogRepository->findLatest(100);

        $data = [];

        foreach ($logs as $log) {
            $data[] = [
                'id' => (string) $log->getId(),
                'code' => $log->getCode(),
                'level' => $log->getLevel(),
                'message' => $log->getMessage(),
                'route' => $log->getRoute(),
                'method' => $log->getMethod(),
                'userAgent' => $log->getUserAgent(),
                'contextJson' => $log->getContextJson(),
                'requestIp' => $log->getRequestIp(),
                'createdAt' => $log->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/audit', name: 'audit', methods: ['GET'])]
    public function audit(AuditLogRepository $auditLogRepository): JsonResponse
    {
        $logs = $auditLogRepository->findLatest(100);

        $data = [];

        foreach ($logs as $log) {
            $data[] = [
                'id' => (string) $log->getId(),
                'actorUsername' => $log->getActorUsername(),
                'actorEmail' => $log->getActorEmail(),
                'action' => $log->getAction(),
                'entityType' => $log->getEntityType(),
                'entityId' => $log->getEntityId(),
                'oldValuesJson' => $log->getOldValuesJson(),
                'newValuesJson' => $log->getNewValuesJson(),
                'requestIp' => $log->getRequestIp(),
                'userAgent' => $log->getUserAgent(),
                'createdAt' => $log->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }
}