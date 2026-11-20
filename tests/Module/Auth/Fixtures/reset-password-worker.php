<?php

// Runs in a separate process so PostgreSQL's real row locks are exercised.
require dirname(__DIR__, 4).'/vendor/autoload.php';
require dirname(__DIR__).'/PasswordResetPostgresTest.php';

use App\Module\Auth\Service\PasswordResetService;
use App\Tests\Module\Auth\PasswordResetPostgresTest;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

[$script, $schema, $token, $password] = $argv;
if (!preg_match('/\Apassword_reset_test_[0-9a-f]{16}\z/', $schema)) {
    throw new InvalidArgumentException('Invalid test schema.');
}
$em = PasswordResetPostgresTest::connect();
$db = $em->getConnection();
$db->executeStatement('SET search_path TO '.$schema);
$db->executeStatement("SET lock_timeout TO '20s'");
$hasher = new class implements UserPasswordHasherInterface {
    private UserPasswordHasherInterface $inner;

    public function __construct()
    {
        $this->inner = PasswordResetPostgresTest::passwordHasher();
    }

    public function hashPassword(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): string
    {
        $hash = $this->inner->hashPassword($user, $plainPassword);
        echo "ready\n";
        flush();

        return $hash;
    }

    public function isPasswordValid(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): bool
    {
        return $this->inner->isPasswordValid($user, $plainPassword);
    }

    public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
    {
        return $this->inner->needsRehash($user);
    }
};
try {
    $service = new PasswordResetService($em, $hasher, new Mailer(new NullTransport()), new UrlGenerator(new RouteCollection(), new RequestContext()), 'http://localhost', 'no-reply@example.test');
    echo 'result:'.(int) $service->resetPassword($token, 'reset@example.test', $password)."\n";
} finally {
    $db->close();
}
