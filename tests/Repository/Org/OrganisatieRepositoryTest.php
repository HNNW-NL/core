<?php

namespace App\Tests\Repository\Org;

use App\Repository\Org\OrganisatieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrganisatieRepositoryTest extends KernelTestCase
{
    private ?OrganisatieRepository $organisationRepository;
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->organisationRepository =$container
            ->get(OrganisatieRepository::class);
        $this->entityManager  = $container->get(EntityManagerInterface::class);
    }


    // countActive

    public function testWhenAllProjectNotDeletedShouldReturnCountAllProjects(): void
    {
        $result = $this->organisationRepository->countActive();

        $this->assertEquals(1,$result);
    }

    public function testWhenProjectIsDeletedShouldNotBeCounted(): void
    {
        $account = $this->organisationRepository->findOneBy(['slug' => 'Test-Organistion']);

        $account->softDelete();
        $this->entityManager->flush();

        $result = $this->organisationRepository->countActive();

        $this->assertEquals(0,$result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->organisationRepository = null;
        $this->entityManager = null;
    }
}
