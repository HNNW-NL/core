<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Module\Main\Service\ProjectApplyService;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProjectApplyAvailabilityTest extends TestCase
{
    public function testExpiredProjectIsClosedForApplications(): void
    {
        $project = $this->createProject('published');
        $project->setEndDate(new \DateTimeImmutable('yesterday'));

        self::assertFalse($this->createService()->isOpen($project));
    }

    public function testClosedProjectStatusBlocksApplications(): void
    {
        $project = $this->createProject('closed');

        self::assertFalse($this->createService()->isOpen($project));
    }

    public function testPublishedProjectWithoutCapacityOrEndDateRemainsOpen(): void
    {
        $project = $this->createProject('published');

        self::assertTrue($this->createService()->isOpen($project));
    }

    private function createProject(string $statusName): Project
    {
        $project = (new Project())
            ->setStatus((new Status())->setName($statusName))
            ->setVisibility('public')
            ->setOwnerOrganisation(new Organisation());
        $project->publish();

        return $project;
    }

    private function createService(): ProjectApplyService
    {
        return new ProjectApplyService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(StatusRepository::class),
            $this->createStub(ProjectParticipantRepository::class),
        );
    }
}
