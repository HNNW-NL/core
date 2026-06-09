<?php

namespace App\Module\Org\Handler;

use App\Entity\Account\Account;
use App\Module\Org\DTO\ModifyProjectDTO;
use App\Module\Org\Mapper\ModifyProjectMapper;
use App\Module\Org\Service\ModifyProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class ModifyProjectHandler
{
    public function __construct(
        private ModifyProjectService $service,
        private ModifyProjectMapper $mapper,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    // Main handle method - coordinates everything
    public function handle(Request $request, Account $publisher): array
    {
        try {
            // 1. Start transaction (if needed)
            $this->entityManager->beginTransaction();

            // 2. Convert request to DTO
            $dto = $this->mapper->fromRequest($request, ['publisher' => $publisher]);

            // 3. Basic validation
            if ($dto->projectId <= 0) {
                throw new \InvalidArgumentException('Invalid project ID');
            }

            // 4. Process via service
            $project = $this->service->modify($dto);

            // 5. Commit transaction
            $this->entityManager->commit();

            // 6. Log success
            $this->logger->info('Project modified', [
                'project_id' => $project->getId(),
                'publisher' => $publisher->getEmail(),
            ]);

            // 7. Return success response
            return $this->mapper->toResponse($project);

        } catch (\InvalidArgumentException $e) {
            $this->entityManager->rollback();
            $this->logger->warning($e->getMessage());
            return $this->mapper->toErrorResponse($e->getMessage(), 400);

        } catch (\RuntimeException $e) {
            $this->entityManager->rollback();
            $this->logger->error($e->getMessage());
            return $this->mapper->toErrorResponse($e->getMessage(), 403);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->critical('Unexpected error: ' . $e->getMessage());
            return $this->mapper->toErrorResponse('Internal server error', 500);
        }
    }

    public function quickHandle(Request $request, Account $publisher): array
    {
        try {
            $dto = $this->mapper->fromRequest($request, ['publisher' => $publisher]);
            $project = $this->service->modify($dto);

            return $this->mapper->toResponse($project);

        } catch (\Exception $e) {
            return $this->mapper->toErrorResponse($e->getMessage());
        }
    }
}
