<?php

namespace App\Tests\Module\AccountCentre\Controller;

use App\Entity\Account\{Account, Notification};
use App\Entity\Project\{Project, ProjectApplication, ProjectParticipant, ProjectRole};
use App\Security\ProjectPermission;
use App\Tests\Module\Auth\Fixtures\AuthWebTestCase;

final class AccountDashboardSecurityPostgresTest extends AuthWebTestCase
{
    public function testAnonymousDashboardRedirectsToLogin(): void
    {
        $client = $this->browser();
        $client->request('GET', '/account?account_id=forged');
        self::assertResponseRedirects('/auth/login');
    }

    public function testIdentityQueriesTotalsAndLinkedListsStayWithinTheCurrentAccount(): void
    {
        $actor = $this->account('dashboard-owner');
        $other = $this->account('another-owner');
        $this->application($actor, 'Own pending', 'send');
        $this->application($actor, 'Own accepted', 'accepted');
        $deleted = $this->application($actor, 'Deleted application', 'send');
        $deleted->softDelete();
        $this->participation($actor, $this->project($actor, 'Own project one'));
        $this->participation($actor, $this->project($actor, 'Own project two'));
        $left = $this->participation($actor, $this->project($actor, 'Left project'));
        $left->leave();
        $this->notice($actor, 'Own unread');
        $read = $this->notice($actor, 'Read notice'); $read->markAsRead();
        $expired = $this->notice($actor, 'Expired notice'); $expired->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->application($other, 'Other private application', 'send');
        $this->participation($other, $this->project($other, 'Other private project'));
        $this->notice($other, 'Other private notice');
        $this->entityManager->flush();
        $client = $this->browser($actor);
        $client->request('GET', '/account?account_id='.$other->getId().'&profile_id='.$other->getProfile()->getId().'&user_id[]=forged');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#account-name', 'dashboard-owner display');
        self::assertSelectorTextSame('#account-total-applications', '1');
        self::assertSelectorTextSame('#account-total-projects', '2');
        self::assertSelectorTextSame('#account-total-notifications', '1');
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringNotContainsString('another-owner', $client->getResponse()->getContent());
        $this->snapshot($client, 'account-filled');
        $client->request('GET', '/account/applications?pending=1&account_id='.$other->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '#account-applications article');
        self::assertSelectorTextContains('#account-applications', 'Own pending');
        self::assertStringNotContainsString('Other private', $client->getResponse()->getContent());
        $client->request('GET', '/account/projects?profile_id='.$other->getProfile()->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.project-card');
        self::assertStringNotContainsString('Other private', $client->getResponse()->getContent());
        self::assertStringNotContainsString('Left project', $client->getResponse()->getContent());
        $client->request('GET', '/account/notifications?unread=1&account_id='.$other->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '#account-notifications article');
        self::assertSelectorTextContains('#account-notifications', 'Own unread');
        self::assertStringNotContainsString('Other private', $client->getResponse()->getContent());
    }

    public function testEmptyDashboardIsUsableAndEscapesTheProfileName(): void
    {
        $account = $this->account('empty-dashboard', verified: false);
        $account->getProfile()->setDisplayName('<script>alert(1)</script>');
        $this->entityManager->flush();
        $client = $this->browser($account);
        $client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        foreach (['applications', 'projects', 'notifications'] as $name) { self::assertSelectorTextSame('#account-total-'.$name, '0'); }
        self::assertSelectorExists('#account-empty a[href="/account/modify"]');
        self::assertSelectorExists('a[href="/profile"]');
        self::assertSelectorTextContains('#email-status', 'nog niet bevestigd');
        self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        $client->request('GET', '/account/modify?account_id=forged');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="username"][value="empty-dashboard"]');
    }

    public function testMissingProfileDoesNotBreakTheDashboard(): void
    {
        $account = $this->account('missing-profile', withProfile: false);
        $client = $this->browser($account);
        $client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#account-name', 'missing-profile');
        self::assertSelectorExists('#account-empty');
    }

    public function testUnavailableCountersShowAUsefulErrorInsteadOfFalseZeroTotals(): void
    {
        $account = $this->account('dashboard-outage');
        $client = $this->browser($account);
        $this->database->executeStatement('ALTER TABLE notifications RENAME TO notifications_unavailable');
        $client->request('GET', '/account');
        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Retry-After', '60');
        self::assertSelectorTextContains('[role="alert"]', 'tijdelijk niet');
        self::assertSelectorNotExists('#account-total-notifications');
        self::assertStringNotContainsString('SQLSTATE', $client->getResponse()->getContent());
        $this->database->executeStatement('ALTER TABLE notifications_unavailable RENAME TO notifications');
        $client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#account-total-notifications', '0');
    }

    public function testTwentyOneApplicationsArePaginatedAndIdsCannotChangeOwnership(): void
    {
        $actor = $this->account('page-owner');
        $other = $this->account('other-pages');
        for ($index = 1; $index <= 21; ++$index) { $this->application($actor, 'Application '.$index, 'send'); }
        $this->application($other, 'Other private page', 'send');
        $client = $this->browser($actor);
        $client->request('GET', '/account');
        self::assertSelectorTextSame('#account-total-applications', '21');
        $client->request('GET', '/account/applications?pending=1');
        self::assertSelectorCount(20, '#account-applications article');
        $client->click($client->getCrawler()->filter('a[rel="next"]')->link());
        self::assertSelectorCount(1, '#account-applications article');
        self::assertStringNotContainsString('Other private page', $client->getResponse()->getContent());
        $client->request('GET', '/account/applications?pending=1&page[]=bad&account_id='.$other->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(20, '#account-applications article');
    }

    public function testReadingAnotherAccountsNotificationIsForbidden(): void
    {
        $actor = $this->account('notice-owner');
        $other = $this->account('other-notices');
        $own = $this->notice($actor, 'Own notice');
        $private = $this->notice($other, 'Private notice');
        $client = $this->browser($actor);
        $page = $client->request('GET', '/account/notifications?unread=1');
        $form = $page->selectButton('Markeer als gelezen')->form();
        $client->catchExceptions(true);
        $client->request('POST', '/account/notifications/'.$private->getId().'/read', $form->getValues());
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM notifications WHERE is_read = true'));
        $client->submit($form);
        self::assertResponseRedirects('/account/notifications');
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM notifications WHERE is_read = true'));
    }

    public function testAccountAndWorkPackagesUseTheSameSessionAndObjectPermissions(): void
    {
        $actor = $this->account('work-member');
        $owner = $this->account('work-owner');
        $project = $this->project($owner, 'Private work');
        $this->participation($actor, $project);
        $unrelated = $this->project($owner, 'Unrelated work');
        $client = $this->browser();
        $this->login($client, $actor->getEmail());
        $client->followRedirect();
        self::assertSelectorTextSame('#account-name', 'work-member display');
        $client->request('GET', '/projects/'.$project->getSlug().'/work-packages');
        self::assertResponseIsSuccessful();
        $client->catchExceptions(true);
        $client->request('GET', '/projects/'.$unrelated->getSlug().'/work-packages');
        self::assertResponseStatusCodeSame(403);
    }

    private function application(Account $account, string $title, string $status): ProjectApplication
    {
        $application = (new ProjectApplication())->setProject($this->project($account, $title))->setProfile($account->getProfile())
            ->setStatus($this->fixtureStatus('ProjectApplication', $status));
        $this->entityManager->persist($application);
        $this->entityManager->flush();

        return $application;
    }

    private function participation(Account $account, Project $project): ProjectParticipant
    {
        $role = (new ProjectRole())->setProject($project)->setName('Viewer')->setPermissionsMask(ProjectPermission::VIEW->value);
        $participant = (new ProjectParticipant())->setProject($project)->setProfile($account->getProfile())->setRole($role)
            ->setStatus($this->fixtureStatus('ProjectParticipant', 'active'));
        $participant->join();
        $this->entityManager->persist($role);
        $this->entityManager->persist($participant);
        $this->entityManager->flush();

        return $participant;
    }

    private function notice(Account $account, string $title): Notification
    {
        $notice = (new Notification())->setAccount($account)->setType('test')->setTitle($title)->setMessage('Message for '.$title);
        $this->entityManager->persist($notice);
        $this->entityManager->flush();

        return $notice;
    }
}
