<?php

namespace App\Tests\Module\Org\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PanelControllerTest extends WebTestCase
{

    // index

    public function testShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // projects

    public function testShouldDisplayProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // createProject

    public function testWhenNotLoggedInShouldBeRedirectedFromCreateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInShouldDisplayCreateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndSubmitFormShouldCreateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // modifyProject

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // modifyProjectMatching

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectMatching(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }


    // modifyProjectParticipants

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // InviteProjectParticipants

    public function testWhenNotLoggedInShouldBeRedirectedFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldInviteProjectParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }


    // modifyProjectReviews

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectReviews(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }


    // modifyProjectTasks

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerShouldGiveErrorModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // modifyProjectUpdates

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectUpdates(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // modifyProjectWorkPackages

    public function testWhenNotLoggedInShouldBeRedirectedFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerInShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndProjectDoesntExistShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenNotProjectOwnerAndProjectExistAndFormSubmitShouldGiveErrorFromModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndProjectExistShouldDisplayModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectOwnerAndSubmitFormShouldModifyProjectWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // staff

    public function testShouldDisplayStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // viewStaff

    public function testWheStaffDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
    public function testWhenStaffExistShouldDisplayViewStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
