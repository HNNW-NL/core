<?php

namespace App\DataFixtures\Common;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Common\Status;

class StatusFixtures extends Fixture
{

    public const TEST_DEFAULT_ACCOUNT_STATUS_REFERENCE = 'default-status';
    public const TEST_DEFAULT_ORGANISATION_STATUS_REFERENCE = 'default-organisation-status';
    public const TEST_DEFAULT_PROJECT_STATUS_REFERENCE = 'default-project-status';
    public const TEST_DEFAULT_REVIEW_STATUS_REFERENCE = 'default-review-status';
    public const TEST_DEFAULT_ADMIN_STATUS_REFERENCE = 'default-admin-status';
    public const TEST_DEFAULT_PACKAGE_TASK_STATUS_REFERENCE = 'default-package-task-status';
    public const TEST_DEFAULT_WORK_PACKAGE_STATUS_REFERENCE = 'default-work-package-status';
    public const TEST_DEFAULT_PROJECT_APPLICATIONS_STATUS_REFERENCE = 'default-project-applications-status';
    public const TEST_DEFAULT_PROJECT_PARTICIPANTS_STATUS_REFERENCE = 'default-project-participants-status';

    public function load(ObjectManager $manager): void
    {
        $defaultStatus = new Status();
        $defaultStatus->setName('active');
        $defaultStatus->setScope('account');
        $defaultStatus->setColourHex('#10B981');
        $manager->persist($defaultStatus);

        $defaultOrgStatus = new Status();
        $defaultOrgStatus->setName('active');
        $defaultOrgStatus->setScope('organisation');
        $defaultOrgStatus->setColourHex('#10B981');
        $manager->persist($defaultOrgStatus);

        $defaultProjectStatus = new Status();
        $defaultProjectStatus->setName('active');
        $defaultProjectStatus->setScope('project');
        $defaultProjectStatus->setColourHex('#10B981');
        $manager->persist($defaultProjectStatus);


        $defaultReviewStatus = new Status();
        $defaultReviewStatus->setName('active');
        $defaultReviewStatus->setScope('review');
        $defaultReviewStatus->setColourHex('#10B981');
        $manager->persist($defaultReviewStatus);

        $defaultAdminStatus = new Status();
        $defaultAdminStatus->setName('active');
        $defaultAdminStatus->setScope('admin');
        $defaultAdminStatus->setColourHex('#10B981');
        $manager->persist($defaultAdminStatus);

        $defaultPackageTaskStatus = new Status();
        $defaultPackageTaskStatus->setName('active');
        $defaultPackageTaskStatus->setScope('PackageTask');
        $defaultPackageTaskStatus->setColourHex('#10B981');
        $manager->persist($defaultPackageTaskStatus);

        $defaultWorkPackageStatus = new Status();
        $defaultWorkPackageStatus->setName('active');
        $defaultWorkPackageStatus->setScope('WorkPackage');
        $defaultWorkPackageStatus->setColourHex('#10B981');
        $manager->persist($defaultWorkPackageStatus);

        $defaultProjectApplicationStatus = new Status();
        $defaultProjectApplicationStatus->setName('send');
        $defaultProjectApplicationStatus->setScope('ProjectApplication');
        $defaultProjectApplicationStatus->setColourHex('#10B981');
        $manager->persist($defaultProjectApplicationStatus);

        $defaultProjectParticipantStatus = new Status();
        $defaultProjectParticipantStatus->setName('active');
        $defaultProjectParticipantStatus->setScope('ProjectParticipant');
        $defaultProjectParticipantStatus->setColourHex('#10B981');
        $manager->persist($defaultProjectParticipantStatus);

        $manager->flush();

        $this->addReference(self::TEST_DEFAULT_PROJECT_STATUS_REFERENCE, $defaultProjectStatus);
        $this->addReference(self::TEST_DEFAULT_ORGANISATION_STATUS_REFERENCE, $defaultOrgStatus);
        $this->addReference(self::TEST_DEFAULT_ACCOUNT_STATUS_REFERENCE, $defaultStatus); 
        $this->addReference(self::TEST_DEFAULT_REVIEW_STATUS_REFERENCE, $defaultReviewStatus);
        $this->addReference(self::TEST_DEFAULT_ADMIN_STATUS_REFERENCE, $defaultAdminStatus);
        $this->addReference(self::TEST_DEFAULT_PACKAGE_TASK_STATUS_REFERENCE, $defaultPackageTaskStatus);
        $this->addReference(self::TEST_DEFAULT_WORK_PACKAGE_STATUS_REFERENCE, $defaultWorkPackageStatus);
        $this->addReference(self::TEST_DEFAULT_PROJECT_APPLICATIONS_STATUS_REFERENCE, $defaultProjectApplicationStatus);
        $this->addReference(self::TEST_DEFAULT_PROJECT_PARTICIPANTS_STATUS_REFERENCE, $defaultProjectParticipantStatus);
    
    }
}
