<?php

namespace App\Tests\Twig\Components\Shared;

use App\Entity\Log\AuditLog;
use App\Form\Admin\AuditLogSearchType;
use App\Module\Admin\DTO\AuditLogSearchDisplayDTO;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use App\Module\Admin\Service\Paginate\Adapters\PaginateAdapterMode;
use App\Twig\Components\Shared\Search;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use App\Repository\Org\AccountRepository;
use App\Twig\Components\Shared\Search\SearchDTO;
use BadFunctionCallException;
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

    //postMount

    public function testWhenDisplayDTOHasLabelShouldOverideColumnNames()
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogDisplayDTOTest::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $render = $testComponent->render();

        $this->assertStringContainsString('Gemaakt op',$render);
        $this->assertStringContainsString('Gebruikersnaam',$render);
        $this->assertStringContainsString('Gebruikersemail',$render);
        $this->assertStringContainsString('Actie',$render);
        $this->assertStringContainsString('Entiteittype',$render);
        $this->assertStringContainsString('Entiteitid',$render);
        $this->assertStringContainsString('Ip',$render);
    }

    public function testWhenPathParametersShouldSaveFields()
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogDisplayDTOTest::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $components = $testComponent->component();

        $this->assertEquals(['actorEmail' => 'getActorEmail','actorUsername' => 'getActorUsername'],$components->pathParametersFields);
    }

    // search

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

    public function testWhenPathShouldCreateLink()
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogDisplayDTOTest::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        $testComponent->submitForm([],'search');  

       $this->assertStringContainsString('href="/admin/logs?actorEmail=Test@test.nl&amp;actorUsername=TestUser"',$testComponent->render());
    }

    public function testWhenPathParameterMethodIsInvalidShouldGiveBadFunctionCallException()
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = InvalidDisplayDTOTest::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO]
        );

        try {
            $testComponent->submitForm([],'search');  
        }catch(BadFunctionCallException $e)
        {
            $this->assertStringContainsString("Method 'invalidMethod' does not exist",$e->getMessage());
        }

       
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

    // navigate

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

    public function testWhenInOffsetModeMoreThan20ResultsShouldHavePage2AndShowCurrentPage(): void
    {
        $searchDTO = new SearchDTO();
        $searchDTO->dto = AuditLogSearchDTO::class;
        $searchDTO->displayDTO = AuditLogSearchDisplayDTO::class;
        $searchDTO->entity = AuditLog::class;
        $searchDTO->form = AuditLogSearchType::class;
        $searchDTO->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];
        $testComponent = $this->createLiveComponent(
            Search::class, ["searchDTO" => $searchDTO,'mode' => PaginateAdapterMode::OffsetMode]
        );

        $admin = $this->accountRepository->findBy(['username'=> 'TestUser'])[0];
        $input = ['audit_log_search'=>['actor' => $admin->getId()]];
              
        $testComponent->submitForm($input,'search');  
        $component = $testComponent->component();

        $this->assertNotNull($component->links[2]);

        $this->assertStringContainsString('<span class="currentPage">1</span>',$testComponent->render());

        $testComponent->call('navigate',['link' => $component->links[2]]);  
        

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

#[Search\PathParameter('actorEmail'),Search\PathParameter('actorUsername')]
class AuditLogDisplayDTOTest{

   #[Search\Label("Gemaakt op")]
   #[Search\Path('admin.logs')]
   public ?DateTimeImmutable $createdAt = null; 

   #[Search\Label("Gebruikersnaam")]
   public ?string $actorUsername = '';

   #[Search\Label("Gebruikersemail")]
   public ?string $actorEmail = '';

   #[Search\Label("Actie")]
   public ?string $action = '';

   #[Search\Label("Entiteittype")]
   public ?string $entityType = '';

   #[Search\Label("Entiteitid")]
   public ?string $entityId = '';

   #[Search\Label("Ip")]
   public ?string $requestIp = '';
}

#[Search\PathParameter('actorEmail','invalidMethod'),Search\PathParameter('actorUsername')]
class InvalidDisplayDTOTest{

   #[Search\Label("Gemaakt op")]
   #[Search\Path('admin.logs')]
   public ?DateTimeImmutable $createdAt = null; 

   #[Search\Label("Gebruikersnaam")]
   public ?string $actorUsername = '';

   #[Search\Label("Gebruikersemail")]
   public ?string $actorEmail = '';

   #[Search\Label("Actie")]
   public ?string $action = '';

   #[Search\Label("Entiteittype")]
   public ?string $entityType = '';

   #[Search\Label("Entiteitid")]
   public ?string $entityId = '';

   #[Search\Label("Ip")]
   public ?string $requestIp = '';

}
