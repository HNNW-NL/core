<?php

namespace App\Tests\Module\AccountCentre\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AcountsControllerTest extends WebTestCase
{
    // index

    public function testWhenNotLoggedInShouldRedirectFromIndex(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // applications

    public function testWhenNotLoggedInShouldRedirectFromApplications(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/applications');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInAndHaveApplicationsShouldDisplayApplications(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
    public function testWhenLoggedInAndHaveNoApplicationsShouldDisplayNoApplications(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    //applicationsCancel

    public function testWhenLoggedInAndHaveApplicationsShouldRemoveApplication(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndDoesntHaveApplicationsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndNotOwnerofApplicationShouldNotRemoveApplication(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    //availability

    public function testWhenNotLoggedInShouldRedirectFromAvailability(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/availability');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInSubmitFormShouldSaveAvailablity(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormWithTimeMondayChangedShouldSaveMondayTime(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormWithTimeWednesdayChangedShouldSaveWednesdayTime(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormWithTimeFridayChangedShouldSaveFridayTime(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormWithTimeSaturdayEnabledShouldSaveSaturday(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormWithTimeMondayDisabledShouldNotSaveMonday(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    //deleteAvailability

    public function testWhenLoggedInAndHaveAvailabilityShouldRemoveAvailability(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndDoesntHaveAvailabilityShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndNotOwnerofAvailabilityShouldNotRemoveAvailability(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    //experience

    public function testWhenNotLoggedInShouldRedirectFromExperience(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/experience');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldSaveExperience(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndStartTimeIsAfterEndTimeShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndJobTitleIsEmptyTimeShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndOrginazationNameIsEmptyTimeShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndStartDateIsEmptyTimeShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // deleteExperience

    public function testWhenLoggedInAndHaveExperienceShouldRemoveExperience(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndDoesntHaveExperienceShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndNotOwnerofExperienceShouldNotRemoveExperience(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // modify

    public function testWhenNotLoggedInShouldRedirectFromModify(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/modify');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInSubmitFormShouldSaveProfile(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormShouldSaveSettings(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // notifications

    public function testWhenNotLoggedInShouldRedirectFromNotifications(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/modify');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInSubmitFormAndnotifyProjectsEnabledShouldEnableNotifyProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormAndnotifyProjectsDisabledShouldDisableNotifyProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormAndnotifyNewsletterEnabledShouldEnableNewsletter(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInSubmitFormAndnotifyNewsletterDisabledShouldDisableNotifyNewsletter(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // projects

    public function testWhenNotLoggedInShouldRedirectFromProjects(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/projects');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInAndHaveProjectsShouldDisplayProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
    public function testWhenLoggedInAndHaveNoProjectsShouldDisplayNoProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // reputation

    public function testWhenNotLoggedInShouldRedirectFromReputation(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/reputation');

        $this->assertResponseRedirects("/auth/login");
    }

    // reviews

    public function testWhenNotLoggedInShouldRedirectFromReviews(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/reviews');

        $this->assertResponseRedirects("/auth/login");
    }

    // settings

    public function testWhenNotLoggedInShouldRedirectFromSettings(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/settings');

        $this->assertResponseRedirects("/auth/login");
    }

    // signoff

    public function testWhenNotLoggedInShouldRedirectFromSignoff(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/signoff');

        $this->assertResponseRedirects("/auth/login");
    }

    // skills

    public function testWhenNotLoggedInShouldRedirectFromSkills(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/skills');

        $this->assertResponseRedirects("/auth/login");
    }
}
