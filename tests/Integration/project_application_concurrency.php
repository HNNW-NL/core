<?php
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$kernel = new App\Kernel('test', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$mode = $argv[1] ?? 'parent';
if ($mode === 'worker') {
    $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'race-project']);
    $account = $em->getRepository(Account::class)->findOneBy(['username' => 'race-applicant']);
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
        $active = (new Status())->setScope('account')->setName('active')->setColourHex('#10b981');
        $pending = (new Status())->setScope('ProjectApplication')->setName('send')->setColourHex('#10b981');
        $projectStatus = (new Status())->setScope('project')->setName('active')->setColourHex('#10b981');
        $owner = (new Account())->setUsername('race-owner')->setEmail('race-owner@example.test')->setPasswordHash('unused')->setStatus($active);
        $account = (new Account())->setUsername('race-applicant')->setEmail('race-applicant@example.test')->setPasswordHash('unused')->setStatus($active);
        $account->verifyEmail();
        $profile = (new Profile())->setAccount($account)->setFirstName('Applicant')->setLastName('Test')->setDisplayName('Applicant')->setAvatarUrl('');
        $account->setProfile($profile);
        $org = (new Organisation())->setName('Testorganisatie')->setSlug('race-org')->setStatus($active);
        $project = (new Project())->setOwnerAccount($owner)->setOwnerOrganisation($org)->setStatus($projectStatus)
            ->setTitle('Aanvraagproject')->setSlug('race-project')->setVisibility('public')->setSummary('Test')
            ->setDescription('Test')->setCapacity(10)->setStartDate(new \DateTimeImmutable())->setEndDate(null);
        $project->publish();
        foreach ([$active, $pending, $projectStatus, $owner, $account, $profile, $org, $project] as $entity) { $em->persist($entity); }
        $em->flush();

try {
    $first = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, 'worker', 'hold'], dirname(__DIR__, 2));
    $first->start();
    $first->waitUntil(static fn ($type, $output) => str_contains($output, 'LOCKED'));
    $second = new Symfony\Component\Process\Process([PHP_BINARY, __FILE__, 'worker'], dirname(__DIR__, 2));
    $second->start();
    $first->wait();
    $second->wait();
    if (!$first->isSuccessful() || !$second->isSuccessful()) {
        throw new RuntimeException($first->getErrorOutput().$second->getErrorOutput());
    }
    preg_match('/ID=([A-Z0-9]+)/', $first->getOutput(), $one);
    preg_match('/ID=([A-Z0-9]+)/', $second->getOutput(), $two);
    $em->clear();
    $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'race-project']);
    $count = $em->getRepository(ProjectApplication::class)->count(['project' => $project]);
    if (empty($one[1]) || $one[1] !== ($two[1] ?? null) || $count !== 1) {
        throw new RuntimeException('Gelijktijdige aanvragen leverden geen identieke, unieke aanvraag op.');
    }
    echo "PASS: twee overlappende transacties geven dezelfde aanvraag; database bevat precies 1 aanvraag.\n";
} finally {
    $em->clear();
    $project = $em->getRepository(Project::class)->findOneBy(['slug' => 'race-project']);
    if ($project !== null) {
        foreach ($em->getRepository(ProjectApplication::class)->findBy(['project' => $project]) as $application) { $em->remove($application); }
        $em->flush();
        $em->remove($project);
        $em->flush();
    }
    $org = $em->getRepository(Organisation::class)->findOneBy(['slug' => 'race-org']);
    $account = $em->getRepository(Account::class)->findOneBy(['username' => 'race-applicant']);
    $owner = $em->getRepository(Account::class)->findOneBy(['username' => 'race-owner']);
    if ($account?->getProfile()) { $em->remove($account->getProfile()); $em->flush(); }
    foreach ([$org, $account, $owner] as $entity) { if ($entity !== null) { $em->remove($entity); } }
    $em->flush();
    foreach ([$active, $pending, $projectStatus] as $status) { $em->remove($em->find(Status::class, $status->getId())); }
    $em->flush();
}