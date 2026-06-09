<?php

namespace App\Module\Org\Mapper;

use App\Entity\Project\Project;
use App\Entity\Common\Status;
use App\Module\Org\DTO\ModifyProjectDTO;
use Symfony\Component\HttpFoundation\Request;

class ModifyProjectMapper
{
    // Convert HTTP request to DTO
    public function fromRequest(Request $request, array $extraData = []): ModifyProjectDTO
    {
        return new ModifyProjectDTO(
            projectId: (int)$request->request->get('project_id', $extraData['project_id'] ?? 0),
            organisationId: (int)$request->request->get('organisation_id', $extraData['organisation_id'] ?? 0),
            status: $this->getStatusFromString($request->request->get('status', 'draft')),
            notifyTeam: (bool)$request->request->get('notify_team', false),
            publishMessage: $request->request->get('publish_message'),
            notificationEmail: $request->request->get('notification_email'),
            scheduledFor: $this->getDateFromString($request->request->get('scheduled_for')),
            publishedBy: $extraData['publisher'] ?? null,
        );
    }

    // Convert DTO to Entity updates
    public function toEntity(Project $project, ModifyProjectDTO $dto): Project
    {
        $project->setStatus($dto->status);

        if ($dto->shouldPublishNow()) {
            $project->setPublishedAt(new \DateTimeImmutable());
            $project->setPublishedBy($dto->publishedBy);
            $project->setScheduledFor(null);
        }

        if ($dto->shouldSchedule()) {
            $project->setScheduledFor($dto->scheduledFor);
            $project->setPublishedAt(null);
        }

        if ($dto->status === Status::DRAFT) {
            $project->setPublishedAt(null);
            $project->setScheduledFor(null);
            $project->setPublishedBy(null);
        }

        return $project;
    }

    // Convert Entity to API response
    public function toResponse(Project $project): array
    {
        return [
            'success' => true,
            'id' => $project->getId(),
            'status' => $project->getStatus()->value,
            'published_at' => $project->getPublishedAt()?->format('Y-m-d H:i:s'),
            'url' => '/projects/' . $project->getId(),
        ];
    }

    // Convert error to response
    public function toErrorResponse(string $error, int $code = 400): array
    {
        return [
            'success' => false,
            'error' => $error,
            'code' => $code,
        ];
    }

    // Helper methods
    private function getStatusFromString(?string $status): Status
    {
        return match($status) {
            'published' => Status::PUBLISHED,
            'scheduled' => Status::SCHEDULED,
            'archived' => Status::ARCHIVED,
            default => Status::DRAFT,
        };
    }

    private function getDateFromString(?string $date): ?\DateTimeImmutable
    {
        if (!$date) return null;

        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}
