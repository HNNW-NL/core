<?php

// Separate processes exercise real PostgreSQL transactions; no HTTP routes are added.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use App\Entity\Account\Account;
use App\Module\Auth\Exception\RegistrationValidationException;
use App\Module\Auth\Service\{PasswordResetService, RegistrationService};
use Doctrine\DBAL\{DriverManager, Exception\UniqueConstraintViolationException};
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\PasswordHasher\Hasher\{PasswordHasherFactory, UserPasswordHasher};
use Symfony\Component\Routing\{Generator\UrlGenerator, RequestContext, Route, RouteCollection};
use Symfony\Component\Validator\Validation;

try {
    [$script, $mode, $schema, $number] = $argv;
    $parameters = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse(getenv('AUTH_TEST_DATABASE_URL'));
    if (!preg_match('/\Aauth_security_test_[0-9a-f]{16}\z/', $schema)
        || !in_array($mode, ['reset', 'register'], true)
        || ($parameters['driver'] ?? null) !== 'pdo_pgsql'
        || !in_array($parameters['host'] ?? null, ['localhost', '127.0.0.1', '::1'], true)) {
        throw new InvalidArgumentException('Invalid isolated test database.');
    }
    if (!Type::hasType(UlidType::NAME)) { Type::addType(UlidType::NAME, UlidType::class); }
    $configuration = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 4).'/src/Entity']);
    $configuration->enableNativeLazyObjects(true);
    $configuration->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
    $database = DriverManager::getConnection($parameters);
    $database->executeStatement('SET search_path TO '.$schema);
    $database->executeStatement("SELECT set_config('application_name', ?, false)", [$schema.'_'.$number]);
    $manager = new EntityManager($database, $configuration);
    $routes = new RouteCollection();
    $routes->add('auth.verifyEmail', new Route('/auth/verify-email/{token}/{email}'));
    $router = new UrlGenerator($routes, new RequestContext());
    $mailer = new Mailer(new NullTransport());
    $hasher = new UserPasswordHasher(new PasswordHasherFactory([Account::class => ['algorithm' => 'bcrypt', 'cost' => 4]]));
    echo "READY\n";
    flush();
    if (trim(fgets(STDIN) ?: '') !== 'go') { throw new RuntimeException('Missing start signal.'); }
    if ($mode === 'reset') {
        $service = new PasswordResetService($manager, $mailer, $router, 'https://trusted.example.test', 'no-reply@example.test', $hasher);
        $success = $service->resetPassword(getenv('AUTH_TEST_RESET_TOKEN'), 'reset-user@example.test', 'Concurrent-password-'.$number);
    } else {
        $service = new RegistrationService($manager, Validation::createValidator(), $hasher, $mailer, $router, 'https://trusted.example.test', 'no-reply@example.test');
        try {
            $service->register(['full_name' => 'Concurrent person', 'username' => 'concurrent-'.$number, 'email' => $number === '1' ? ' Concurrent@Example.Test ' : 'concurrent@example.test'], 'Concurrent-password', 'Concurrent-password', true);
            $success = true;
        } catch (UniqueConstraintViolationException|RegistrationValidationException) {
            $success = false;
        }
    }
    echo json_encode(['success' => $success], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    // Never expose connection credentials, token values or exception traces in test output.
    fwrite(STDERR, $exception::class."\n");
    exit(1);
}
