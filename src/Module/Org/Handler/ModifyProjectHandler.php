<?php

namespace App\Module\Org\Handler;

use App\Entity\Account\Account;
use App\Module\Org\Mapper\ModifyProjectMapper;
use App\Module\Org\Service\ModifyProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ModifyProjectHandler
{
    public function __construct(
        private ModifyProjectService $service,
        private ModifyProjectMapper $mapper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function handle(Request $request, ?Account $publisher): array
    {
        return $this->executeModify($request, $publisher);
    }

    private function executeModify(Request $request, ?Account $publisher): array
    {
        $intent = trim((string) ($request->request->get('intent') ?? $request->query->get('intent') ?? ''));
        $organisationId = trim((string) ($request->request->get('organisation_id') ?? $request->query->get('organisation_id') ?? $request->attributes->get('organisation_id') ?? ''));
        $projectRef = trim((string) ($request->request->get('project_id') ?? $request->query->get('project_id') ?? $request->attributes->get('id') ?? ''));
        $logContext = [
            'intent' => $intent,
            'organisation_id' => $organisationId,
            'project_ref' => $projectRef,
            'publisher' => $publisher instanceof Account ? $publisher->getEmail() : null,
        ];

        try {
            $this->entityManager->beginTransaction();

            $extraData = [];
            if ($publisher instanceof Account) {
                $extraData['publisher'] = $publisher;
            }
            $attributeOrganisationId = $request->attributes->get('organisation_id');
            if (is_scalar($attributeOrganisationId)) {
                $attributeOrganisationId = trim((string) $attributeOrganisationId);
                if ($attributeOrganisationId !== '') {
                    $extraData['organisation_id'] = $attributeOrganisationId;
                }
            }

            $dto = $this->mapper->fromRequest($request, $extraData);

            $project = $this->service->modify($dto);

            $this->entityManager->commit();

            $this->logger->info('Project modified', [
                'project_id' => (string) $project->getId(),
                ...$logContext,
            ]);

            return $this->mapper->toResponse($project);

        } catch (\InvalidArgumentException $e) {
            $this->safeRollback();
            $this->logger->warning($e->getMessage(), $logContext);
            return $this->mapper->toErrorResponse($e->getMessage(), 400);

        } catch (\RuntimeException $e) {
            $this->safeRollback();
            $this->logger->error($e->getMessage(), $logContext);
            return $this->mapper->toErrorResponse($e->getMessage(), $this->resolveRuntimeStatusCode($e));

        } catch (\Exception $e) {
            $this->safeRollback();
            $this->logger->critical('Unexpected error: ' . $e->getMessage(), $logContext);
            return $this->mapper->toErrorResponse('Internal server error', 500);
        }
    }

    private function safeRollback(): void
    {
        try {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to rollback transaction: ' . $e->getMessage());
        }
    }

    private function resolveRuntimeStatusCode(\RuntimeException $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return 400;
    }
}
