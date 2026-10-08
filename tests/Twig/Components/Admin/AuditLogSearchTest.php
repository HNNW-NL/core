<?php

namespace App\Tests\Twig\Components\Admin;

use App\Repository\Org\AccountRepository;
use App\Twig\Components\Admin\AuditLogSearch;
use DateTime;
use DateTimeImmutable;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AuditLogSearchTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    private ?AccountRepository $accountRepository;

    protected function setUp(): void
    {
        $this->accountRepository = static::getContainer()
            ->get(AccountRepository::class);
    }

    public function testShouldRenderComponent(): void
    {
        $testComponent = $this->createLiveComponent(
            name: AuditLogSearch::class,
        );

        $this->assertStringContainsString('Klik op zoeken om audit logs te vinden',$testComponent->render());
    }


    #[DataProvider('provideSearchData')]
    public function testWhenSearchingShouldReturnResults(string $expectedResult ,array $input): void
    {
        $testComponent = $this->createLiveComponent(
            name: AuditLogSearch::class,
        );

        $testComponent->submitForm($input,'search');  

        $this->assertStringContainsString($expectedResult,$testComponent->render());
    }

    public static function provideSearchData()
    {        
        return array(
           "When Empty search Should returns results" => ['test',['audit_log_search' => [],'search']],
           "When Startdate today Should return no results" => ['Geen audit logs gevonden',['audit_log_search'=>['startPeriod' => new DateTimeImmutable()->format(DateTime::RFC3339)]]],
          );
    }

    public function testWhenEndateBeforeStartDateShouldReturnValidationError(): void
    {
        $testComponent = $this->createLiveComponent(
            name: AuditLogSearch::class,
        );
         $input = ['audit_log_search'=>['startPeriod' => new DateTimeImmutable()->format(DateTime::RFC3339),'endPeriod' => new DateTimeImmutable('now')->modify('-1 days')->format(DateTime::RFC3339)]];
        
        try{       
            $testComponent->submitForm($input,'search');  
        }catch(UnprocessableEntityHttpException $e){
            $this->assertStringContainsString('De einddatum moet na of hetzelfde als de startdatum zijn',$e->getMessage());
        }
    }

    public function testWhenAdminUserSearchedShouldReturnNoResults(): void
    {
        $testComponent = $this->createLiveComponent(
            name: AuditLogSearch::class,
        );

        $admin = $this->accountRepository->findBy(['username'=> 'AdminUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        
        $this->assertStringContainsString('Geen audit logs gevonden',$testComponent->render());
    }

    public function testWhenNoResultsShouldNotHaveNextAndPrev(): void
    {
        $testComponent = $this->createLiveComponent(
            name: AuditLogSearch::class,
        );

        $admin = $this->accountRepository->findBy(['username'=> 'AdminUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        $component = $testComponent->component();


        $this->assertFalse($component->hasPrev);
        $this->assertFalse($component->hasNext);
    }

    public function testWhenMoreThan20ResultsShouldHaveNextPage(): void
    {
        $testComponent = $this->createLiveComponent(
            name: AuditLogSearch::class,
        );

        $admin = $this->accountRepository->findBy(['username'=> 'TestUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        $component = $testComponent->component();

        $this->assertTrue($component->hasNext);

        $testComponent->call('next');
        

        $this->assertStringContainsString('test20',$testComponent->render());
    }

    public function testWhenMoreThan20ResultsShouldBeAbleToReturnToFirstPage(): void
    {
        $testComponent = $this->createLiveComponent(
            name: AuditLogSearch::class,
        );

        $admin = $this->accountRepository->findBy(['username'=> 'TestUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        $testComponent->call('next');        
        $component = $testComponent->component();

        $this->assertTrue($component->hasPrev);
        
        $testComponent->call('prev');

        $this->assertStringContainsString('test',$testComponent->render());
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->accountRepository = null;
    }
}
