<?php

namespace App\DataFixtures\Account;

use App\DataFixtures\Common\StatusFixtures;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Account\Review;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReviewFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $review = new Review();
        $review->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $review->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE, Project::class));
        $review->setReviewerProfile($this->getReference(ProfileFixtures::TEST_ADMIN_PROFILE_REFERENCE, Profile::class));
        $review->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_REVIEW_STATUS_REFERENCE, Status::class));
        $review->setMessage("A test review");

        $manager->persist($review);

        $manager->flush();
        
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\ProfileFixtures',
            'App\DataFixtures\Common\StatusFixtures',
            'App\DataFixtures\Project\ProjectFixtures'
        );
    }
}
