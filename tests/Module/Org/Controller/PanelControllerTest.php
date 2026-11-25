<?php

namespace App\Tests\Module\Org\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PanelControllerTest extends WebTestCase
{

    // index

    public function testShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // projects

    public function testShouldDisplayProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // createProject

    public function testWhenNotLoggedInShouldBeRedirectedFromCreateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayCreateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndSubmitFormShouldCreateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // modifyProject

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // modifyProjectMatching

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // modifyProjectParticipants

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // InviteProjectParticipants

    public function testWhenNotLoggedInShouldBeRedirectedFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // modifyProjectReviews

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // modifyProjectTasks

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // modifyProjectUpdates

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // modifyProjectWorkPackages

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerInShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // staff

    public function testShouldDisplayStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // viewStaff

    public function testWheStaffDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
    public function testWhenStaffExistShouldDisplayViewStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
