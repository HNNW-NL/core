<?php

namespace App\Tests\Repository\Project;

use App\Repository\Project\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProjectRepositoryTest extends KernelTestCase
{
    private ?ProjectRepository $projectRepository;
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->projectRepository =$container
            ->get(ProjectRepository::class);
        $this->entityManager  = $container->get(EntityManagerInterface::class);
    }

    // findOneVisibleBySlug

    public function testWhenProjectIsNotDeletedShouldReturnProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectIsDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectDoesntExistShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // countActive

    public function testWhenAllProjectNotDeletedShouldReturnCountAllProjects(): void
    {
        $result = $this->projectRepository->countActive();

        $this->assertEquals(1,$result);
    }

    public function testWhenProjectIsDeletedShouldNotBeCounted(): void
    {
        $account = $this->projectRepository->findOneBy(['slug' => 'Test-Project']);

        $account->softDelete();
        $this->entityManager->flush();

        $result = $this->projectRepository->countActive();

        $this->assertEquals(0,$result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->projectRepository = null;
        $this->entityManager = null;
    }
}
