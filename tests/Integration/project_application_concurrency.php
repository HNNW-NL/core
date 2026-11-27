<?php
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Kernel('test', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$mode = $argv[1] ?? 'parent';
if (in_array($mode, ['worker', 'accept-worker'], true)) {
    $project = $em->getRepository(Project::class)->find(new Symfony\Component\Uid\Ulid($argv[3]));
    $account = $em->getRepository(Account::class)->find(new Symfony\Component\Uid\Ulid($argv[4]));
    if ($mode === 'accept-worker') {
        // Preload the pending request before waiting for another transaction.
        $em->find(ProjectApplication::class, new Symfony\Component\Uid\Ulid($argv[5]));
        if (($argv[2] ?? '') !== 'hold') {
            file_put_contents($argv[6], 'preloaded');
        }
    }
    $service = new App\Module\Main\Service\ProjectApplicationService($em, new App\Repository\Common\StatusRepository($kernel->getContainer()->get('doctrine')), new App\Repository\Project\ProjectParticipantRepository($kernel->getContainer()->get('doctrine')));
    if (($argv[2] ?? '') === 'hold') {
        $em->beginTransaction();
        $em->lock($project, Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        echo "LOCKED\n";
        flush();
        if ($mode === 'accept-worker') {
            $deadline = microtime(true) + 10;
            while (!is_file($argv[6])) {
                if (microtime(true) >= $deadline) { throw new RuntimeException('Second acceptance did not preload the request.'); }
                usleep(10000);
            }
        } else {
            usleep(1500000);
        }
    }
    if ($mode === 'accept-worker') {
        $registry = $kernel->getContainer()->get('doctrine');
        $handler = new App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler(
            new App\Repository\Project\ProjectParticipantRepository($registry),
            new App\Repository\Project\ProjectRoleRepository($registry), $em,
            new App\Repository\Common\StatusRepository($registry),
        );
        $result = $handler->acceptApplication($project, $argv[5], $account->getProfile());
        if (($argv[2] ?? '') === 'hold') { $em->commit(); }
        echo 'SUCCESS='.($result['success'] ? '1' : '0')."\n";
    } else {
        $application = $service->submit($project, $account, $account->getProfile(), 'Een geldige motivatie voor de gelijktijdige test.');
        if (($argv[2] ?? '') === 'hold') { $em->commit(); }
        echo 'ID='.(string) $application->getId()."\n";
    }
    exit;
}
$runId = bin2hex(random_bytes(8));
$statuses = $em->getRepository(Status::class);
$active = $statuses->findOneBy(['scope' => 'account', 'name' => 'active']) ?? (new Status())->setScope('account')->setName('active')->setColourHex('#10b981');
$pending = $statuses->findOneBy(['scope' => 'ProjectApplication', 'name' => 'send']) ?? (new Status())->setScope('ProjectApplication')->setName('send')->setColourHex('#10b981');
$projectStatus = $statuses->findOneBy(['scope' => 'project', 'name' => 'active']) ?? (new Status())->setScope('project')->setName('active')->setColourHex('#10b981');
$owner = (new Account())->setUsername('race-owner-'.$runId)->setEmail('race-owner-'.$runId.'@example.test')->setPasswordHash('unused')->setStatus($active);
$account = (new Account())->setUsername('race-applicant-'.$runId)->setEmail('race-applicant-'.$runId.'@example.test')->setPasswordHash('unused')->setStatus($active);
$account->verifyEmail();
$profile = (new Profile())->setAccount($account)->setFirstName('Applicant')->setLastName('Test')->setDisplayName('Applicant')->setAvatarUrl('');
$account->setProfile($profile);
$org = (new Organisation())->setName('Testorganisatie')->setSlug('race-org-'.$runId)->setStatus($active);
$project = (new Project())->setOwnerAccount($owner)->setOwnerOrganisation($org)->setStatus($projectStatus)
    ->setTitle('Aanvraagproject')->setSlug('race-project-'.$runId)->setVisibility('public')->setSummary('Test')
    ->setDescription('Test')->setCapacity(10)->setStartDate(new \DateTimeImmutable())->setEndDate(null);
$project->publish();
$capacityRace = $mode === 'capacity-parent';
$accepting = $mode === 'accept-parent' || $capacityRace;
if ($capacityRace) { $project->setCapacity(1); }
$ownerProfile = (new Profile())->setAccount($owner)->setFirstName('Reviewer')->setLastName('Test')->setAvatarUrl('');
$owner->setProfile($ownerProfile);
$accepted = $statuses->findOneBy(['scope' => 'ProjectApplication', 'name' => 'accepted']) ?? (new Status())->setScope('ProjectApplication')->setName('accepted')->setColourHex('#10b981');
$participantActive = $statuses->findOneBy(['scope' => 'ProjectParticipant', 'name' => 'active']) ?? (new Status())->setScope('ProjectParticipant')->setName('active')->setColourHex('#10b981');
$owned = [];
foreach ([$active, $pending, $projectStatus, $accepted, $participantActive, $owner, $account, $profile, $ownerProfile, $org, $project] as $entity) {
    if (!$em->contains($entity)) {
        $owned[] = [$entity::class, $entity->getId()];
        $em->persist($entity);
    }
}
$em->flush();

$projectId = $project->getId();
$accountId = $account->getId();
$applicationId = null;
$secondApplicationId = null;
if ($accepting) {
    $application = (new ProjectApplication())->setProject($project)->setProfile($profile)->setStatus($pending);
    $em->persist($application);
    $em->flush();
    $applicationId = (string) $application->getId();
    if ($capacityRace) {
        $secondAccount = (new Account())->setUsername('race-second-'.$runId)->setEmail('race-second-'.$runId.'@example.test')->setPasswordHash('unused')->setStatus($active);
        $secondProfile = (new Profile())->setAccount($secondAccount)->setFirstName('Second')->setLastName('Applicant')->setAvatarUrl('');
        $secondAccount->setProfile($secondProfile);
        foreach ([$secondAccount, $secondProfile] as $entity) {
            $owned[] = [$entity::class, $entity->getId()];
            $em->persist($entity);
        }
        $secondApplication = (new ProjectApplication())->setProject($project)->setProfile($secondProfile)->setStatus($pending);
        $em->persist($secondApplication);
        $em->flush();
        $secondApplicationId = (string) $secondApplication->getId();
    }
}
$workerMode = $accepting ? 'accept-worker' : 'worker';
$workerAccountId = $accepting ? (string) $owner->getId() : (string) $accountId;
$barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hnnw-accept-'.$runId.'.ready';
$first = $second = null;
try {
    $first = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, $workerMode, 'hold', (string) $projectId, $workerAccountId, $applicationId ?? '', $barrier], dirname(__DIR__, 2));
    $first->start();
    if (!$first->waitUntil(static fn ($type, $output) => str_contains($first->getOutput(), 'LOCKED'))) {
        throw new RuntimeException('Het eerste verzoek heeft de projectlock niet verkregen. '.$first->getErrorOutput());
    }
    $second = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, $workerMode, 'normal', (string) $projectId, $workerAccountId, $secondApplicationId ?? $applicationId ?? '', $barrier], dirname(__DIR__, 2));
    $second->start();
    $first->wait();
    $second->wait();
    if (!$first->isSuccessful() || !$second->isSuccessful()) {
        throw new RuntimeException($first->getErrorOutput().$second->getErrorOutput());
    }
    preg_match('/ID=([A-Z0-9]+)/', $first->getOutput(), $one);
    preg_match('/ID=([A-Z0-9]+)/', $second->getOutput(), $two);
    $em->clear();
    $project = $em->getRepository(Project::class)->find($projectId);
    $count = $em->getRepository(ProjectApplication::class)->count(['project' => $project]);
    if ($accepting) {
        $application = $em->find(ProjectApplication::class, new Symfony\Component\Uid\Ulid($applicationId));
        $participantCount = $em->getRepository(App\Entity\Project\ProjectParticipant::class)->count(['project' => $project]);
        if (!str_contains($first->getOutput(), 'SUCCESS=1') || !str_contains($second->getOutput(), 'SUCCESS=0')
            || $participantCount !== 1 || $application->getStatus()->getName() !== 'accepted') {
            throw new RuntimeException('Concurrent acceptance did not preserve one atomic transition.');
        }
        echo "PASS: concurrent acceptance succeeds once; database contains one accepted application and one participant.\n";
        if ($capacityRace) {
            $secondApplication = $em->find(ProjectApplication::class, new Symfony\Component\Uid\Ulid($secondApplicationId));
            if ($secondApplication->getStatus()->getName() !== 'send') {
                throw new RuntimeException('The applicant exceeding capacity must remain pending.');
            }
            echo "PASS: last available slot is assigned once; other applicant remains pending.\n";
        }
    } else {
        if (empty($one[1]) || $one[1] !== ($two[1] ?? null) || $count !== 1) {
            throw new RuntimeException('Concurrent submissions did not return the same unique application.');
        }
        echo "PASS: database bevat precies 1 aanvraag.\n";
    }
} finally {
    $first?->stop(1);
    $second?->stop(1);
    if (is_file($barrier)) { unlink($barrier); }
    $em->clear();
    $project = $em->find(Project::class, $projectId);
    if ($project !== null) {
        foreach ($em->getRepository(App\Entity\Project\ProjectParticipant::class)->findBy(['project' => $project]) as $participant) { $em->remove($participant); }
        $em->flush();
        foreach ($em->getRepository(ProjectApplication::class)->findBy(['project' => $project]) as $application) {
            $em->remove($application);
        }
        $em->flush();
    }
    // Alleen IDs van records die dit proces zelf heeft aangemaakt.
    foreach (array_reverse($owned) as [$class, $id]) {
        $entity = $em->find($class, $id);
        if ($entity !== null) {
            $em->remove($entity);
            $em->flush();
        }
    }
}
