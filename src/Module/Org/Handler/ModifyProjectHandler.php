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
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

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
        // Capture the request intent and ids once so every log entry and error response has the same context.
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
            // Begin a transaction so request parsing, validation, and persistence succeed or fail together.
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
            // Input problems become 400 responses because the request data itself is invalid.
            $this->safeRollback();
            $this->logger->warning($e->getMessage(), $logContext);
            return $this->mapper->toErrorResponse($e->getMessage(), self::HTTP_BAD_REQUEST);

        } catch (\RuntimeException $e) {
            // Domain errors may carry a more specific HTTP status, such as 404 or 403.
            $this->safeRollback();
            $this->logger->error($e->getMessage(), $logContext);
            return $this->mapper->toErrorResponse($e->getMessage(), $this->resolveRuntimeStatusCode($e));

        } catch (\Exception $e) {
            // Any unexpected exception is treated as a server-side failure.
            $this->safeRollback();
            $this->logger->critical('Unexpected error: ' . $e->getMessage(), $logContext);
            return $this->mapper->toErrorResponse('Internal server error', self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function safeRollback(): void
    {
        try {
            // Roll back only if a transaction is active, and never let rollback errors hide the original one.
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to rollback transaction: ' . $e->getMessage());
        }
    }

    private function resolveRuntimeStatusCode(\RuntimeException $exception): int
    {
        // Keep the status code from HttpException; otherwise fall back to 400 for generic runtime failures.
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return self::HTTP_BAD_REQUEST;
    }
}
