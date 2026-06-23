<?php

namespace App\Module\Org\Handler;

use App\Entity\Account\Account;
use App\Module\Org\Mapper\ModifyProjectMapper;
use App\Module\Org\Service\ModifyProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Exception as DBALException;

class ModifyProjectHandler
{
    public function __construct(
        private ModifyProjectService $service,
        private ModifyProjectMapper $mapper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function handle(Request $request, Account $publisher): array
    {
        try {
            $this->entityManager->beginTransaction();

            $dto = $this->mapper->fromRequest($request, ['publisher' => $publisher]);

            if ($dto->projectId === '') {
                throw new \InvalidArgumentException('Invalid project ID');
            }

            $project = $this->service->modify($dto);

            $this->entityManager->commit();

            $this->logger->info('Project modified', [
                'project_id' => (string) $project->getId(),
                'publisher' => $publisher->getEmail(),
            ]);

            return $this->mapper->toResponse($project);

        } catch (\InvalidArgumentException $e) {
            $this->safeRollback();
            $this->logger->warning($e->getMessage());
            return $this->mapper->toErrorResponse($e->getMessage(), 400);

        } catch (\RuntimeException $e) {
            $this->safeRollback();
            $this->logger->error($e->getMessage());
            return $this->mapper->toErrorResponse($e->getMessage(), 403);

        } catch (\Exception $e) {
            $this->safeRollback();
            $this->logger->critical('Unexpected error: ' . $e->getMessage());
            return $this->mapper->toErrorResponse('Internal server error', 500);
        }
    }

    public function quickHandle(Request $request, Account $publisher): array
    {
        try {
            $this->entityManager->beginTransaction();

            $dto = $this->mapper->fromRequest($request, ['publisher' => $publisher]);

            if ($dto->projectId === '') {
                throw new \InvalidArgumentException('Invalid project ID');
            }

            $project = $this->service->modify($dto);

            $this->entityManager->commit();

            $this->logger->info('Project modified (quick handle)', [
                'project_id' => (string) $project->getId(),
                'publisher' => $publisher->getEmail(),
            ]);

            return $this->mapper->toResponse($project);

        } catch (\InvalidArgumentException $e) {
            $this->safeRollback();
            $this->logger->warning($e->getMessage());
            return $this->mapper->toErrorResponse($e->getMessage(), 400);

        } catch (\Exception $e) {
            $this->safeRollback();
            $this->logger->error('Quick handle error: ' . $e->getMessage());
            return $this->mapper->toErrorResponse($e->getMessage(), 500);
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
}
