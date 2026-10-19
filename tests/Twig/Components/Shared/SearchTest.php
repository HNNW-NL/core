<?php

namespace App\Tests\Twig\Components\Shared;

use App\Entity\Log\AuditLog;
use App\Form\Admin\AuditLogSearchType;
use App\Module\Admin\DTO\AuditLogSearchDisplayDTO;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use App\Twig\Components\Shared\Search;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use App\Repository\Org\AccountRepository;
use App\Twig\Components\Shared\SearchDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use DateTime;
use DateTimeImmutable;
use Override;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SearchTest extends KernelTestCase
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
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $this->assertStringContainsString('Klik op zoeken om resultaten te vinden',$testComponent->render());
    }


    #[DataProvider('provideSearchData')]
    public function testWhenSearchingShouldReturnResults(string $expectedResult ,array $input): void
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $testComponent->submitForm($input,'search');  

        $this->assertStringContainsString($expectedResult,$testComponent->render());
    }

    public static function provideSearchData()
    {        
        return array(
           "When Empty search Should returns results" => ['test',['audit_log_search' => [],'search']],
           "When Startdate today Should return no results" => ['Geen resultaten gevonden',['audit_log_search'=>['startPeriod' => new DateTimeImmutable()->format(DateTime::RFC3339)]]],
          );
    }

    public function testWhenEndateBeforeStartDateShouldReturnValidationError(): void
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
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
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $admin = $this->accountRepository->findBy(['username'=> 'AdminUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        
        $this->assertStringContainsString('Geen resultaten gevonden',$testComponent->render());
    }

    public function testWhenNoResultsShouldNotHaveNextAndPrev(): void
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $admin = $this->accountRepository->findBy(['username'=> 'AdminUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        $component = $testComponent->component();


        $this->assertNull($component->links['prev']);
        $this->assertNull($component->links['next']);
    }

    public function testWhenMoreThan20ResultsShouldHaveNextPage(): void
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $admin = $this->accountRepository->findBy(['username'=> 'TestUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        $component = $testComponent->component();

        $this->assertNotNull($component->links['next']);

        $testComponent->call('navigate',['link' => $component->links['next']]);  
        

        $this->assertStringContainsString('test20',$testComponent->render());
    }

    public function testWhenMoreThan20ResultsShouldBeAbleToReturnToFirstPage(): void
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $admin = $this->accountRepository->findBy(['username'=> 'TestUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];

        $component = $testComponent->component();
        $testComponent->submitForm($input,'search');       
        $component = $testComponent->component();
        $testComponent->call('navigate',['link' => $component->links['next']]);        
        $component = $testComponent->component();

        $this->assertNotNull($component->links['prev']);
        
        $testComponent->call('navigate',['link' => $component->links['prev']]);  

        $this->assertStringContainsString('test',$testComponent->render());
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->accountRepository = null;
    }
}
