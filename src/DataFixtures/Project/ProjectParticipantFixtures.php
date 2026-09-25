<?php

namespace App\DataFixtures\Project;

use App\DataFixtures\Account\ProfileFixtures;
use App\DataFixtures\Common\StatusFixtures;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectParticipantFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $projectParticipant = new ProjectParticipant();
        $projectParticipant->setApplication($this->getReference(ProjectApplicationFixtures::TEST_PROJECT_APPLICATION, ProjectApplication::class));
        $projectParticipant->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $projectParticipant->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE, Project::class));
        $projectParticipant->setRole($this->getReference(ProjectRoleFixtures::TEST_PROJECT_ROLE, ProjectRole::class));
        $projectParticipant->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_PROJECT_PARTICIPANTS_STATUS_REFERENCE, Status::class));
        $manager->persist($projectParticipant);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Project\ProjectApplicationFixtures',
            'App\DataFixtures\Project\ProjectRoleFixtures',
            'App\DataFixtures\Project\ProjectFixtures',
            'App\DataFixtures\Common\StatusFixtures',
            'App\DataFixtures\Account\ProfileFixtures'
        );
    }
}
