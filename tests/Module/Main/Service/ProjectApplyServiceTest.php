<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\Service\ProjectApplicationService;
use App\Module\Main\Service\ProjectApplyService;
use App\Module\Main\Handler\ProjectApplyHandler;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ProjectApplyServiceTest extends TestCase
{
    #[TestWith(['valid'])]
    #[TestWith(['unverified'])]
    #[TestWith(['owner'])]
    #[TestWith(['private'])]
    #[TestWith(['closed'])]
    #[TestWith(['expired'])]
    #[TestWith(['active'])]
    #[TestWith(['inactive'])]
    #[TestWith(['pending'])]
    #[TestWith(['withdrawn'])]
    #[TestWith(['accepted'])]
    #[TestWith(['missing-status'])]
    #[TestWith(['short-motivation'])]
    public function testAllEntryPointsShareTheSubmissionRules(string $scenario): void
    {
        $account = new Account();
        if ($scenario !== 'unverified') { $account->verifyEmail(); }
        $profile = (new Profile())->setAccount($account);
        $project = (new Project())->setVisibility($scenario === 'private' ? 'private' : 'public')
            ->setStatus((new Status())->setScope('project')->setName($scenario === 'closed' ? 'closed' : 'active'))
            ->setEndDate($scenario === 'expired' ? new \DateTimeImmutable('-1 day') : null);
        $project->publish();
        if ($scenario === 'owner') { $project->setOwnerAccount($account); }
        $existing = null;
        if (in_array($scenario, ['pending', 'withdrawn', 'accepted'], true)) {
            $existing = (new ProjectApplication())->setProject($project)->setProfile($profile)
                ->setStatus((new Status())->setScope('ProjectApplication')->setName($scenario === 'accepted' ? 'accepted' : 'send'));
            if ($scenario === 'withdrawn') { $existing->softDelete(); }
        }
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('wrapInTransaction')->willReturnCallback(static fn ($operation) => $operation());
        $success = in_array($scenario, ['valid', 'inactive'], true);
        $em->expects($success ? self::once() : self::never())->method('persist');
        $statuses = $this->createStub(StatusRepository::class);
        $statuses->method('findFirstByScopeAndNames')->willReturn($scenario === 'missing-status' ? null
            : (new Status())->setScope('ProjectApplication')->setName('send'));
        $participants = $this->createStub(ProjectParticipantRepository::class);
        $participants->method('isActiveParticipant')->willReturn($scenario === 'active');
        $central = new ProjectApplicationService($em, $statuses, $participants);
        $service = new ProjectApplyService($central);
        $handler = new ProjectApplyHandler($service);
        self::assertEquals($central->check($project, $profile), $service->check($project, $profile));
        self::assertEquals($central->check($project, $profile), $handler->check($project, $profile));
        self::assertSame(in_array($scenario, ['valid', 'inactive', 'short-motivation'], true), $service->check($project, $profile) === null);
        $dto = new ProjectApplyDto();
        $dto->motivation = $scenario === 'short-motivation' ? 'kort' : 'Een geldige motivatie voor dit project.';
        if ($scenario === 'unverified') { $this->expectException(AccessDeniedHttpException::class); }
        $result = $handler->handle($project, $profile, $dto);
        self::assertSame($success, $result->isSuccessful());
        if ($scenario === 'pending') { self::assertSame($existing, $result->application); }
    }
}
