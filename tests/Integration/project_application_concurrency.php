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
if ($mode === 'worker') {
    $project = $em->getRepository(Project::class)->find(new Symfony\Component\Uid\Ulid($argv[3]));
    $account = $em->getRepository(Account::class)->find(new Symfony\Component\Uid\Ulid($argv[4]));
    $service = new App\Module\Main\Service\ProjectApplicationService($em, new App\Repository\Common\StatusRepository($kernel->getContainer()->get('doctrine')));
    if (($argv[2] ?? '') === 'hold') {
        $em->beginTransaction();
        $em->lock($project, Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        echo "LOCKED\n";
        flush();
        usleep(1500000);
    }
    $application = $service->submit($project, $account, $account->getProfile(), 'Een geldige motivatie voor de gelijktijdige test.');
    if (($argv[2] ?? '') === 'hold') { $em->commit(); }
    echo 'ID='.(string) $application->getId()."\n";
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
$owned = [];
foreach ([$active, $pending, $projectStatus, $owner, $account, $profile, $org, $project] as $entity) {
    if (!$em->contains($entity)) {
        $owned[] = [$entity::class, $entity->getId()];
        $em->persist($entity);
    }
}
$em->flush();

$projectId = $project->getId();
$accountId = $account->getId();
$first = $second = null;
try {
    $first = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, 'worker', 'hold', (string) $projectId, (string) $accountId], dirname(__DIR__, 2));
    $first->start();
    if (!$first->waitUntil(static fn ($type, $output) => str_contains($first->getOutput(), 'LOCKED'))) {
        throw new RuntimeException('Het eerste verzoek heeft de projectlock niet verkregen.');
    }
    $second = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, 'worker', 'normal', (string) $projectId, (string) $accountId], dirname(__DIR__, 2));
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
    if (empty($one[1]) || $one[1] !== ($two[1] ?? null) || $count !== 1) {
        throw new RuntimeException('Gelijktijdige aanvragen leverden geen identieke, unieke aanvraag op.');
    }
    echo "PASS: twee overlappende transacties geven dezelfde aanvraag; database bevat precies 1 aanvraag.\n";
} finally {
    $first?->stop(1);
    $second?->stop(1);
    $em->clear();
    $project = $em->find(Project::class, $projectId);
    if ($project !== null) {
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
