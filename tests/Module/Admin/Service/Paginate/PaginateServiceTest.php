<?php

namespace App\Tests\Module\Admin\Service\Paginate;

use App\Entity\Account\Account;
use App\Entity\Log\AuditLog;
use App\Entity\Log\SystemLog;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use App\Module\Admin\DTO\SystemLogSearchDTO;
use App\Module\Admin\Service\Paginate\Adapters\PaginateAdapterMode;
use App\Module\Admin\Service\Paginate\PaginateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Module\Admin\Service\Paginate\Attributes as Paginate;
use LogicException;

class PaginateServiceTest extends KernelTestCase
{
    private ?PaginateService $paginateService;
    private ?EntityManager $entityManager;

    protected function setUp(): void
    {
        $this->paginateService = static::getContainer()
            ->get(PaginateService::class);
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    // paginate

    #[DataProvider('providerPaginate')]
    public function testWhenPaginatingShouldCreateWhere(array $expectedResult ,array $input): void
    {
        $this->paginateService->paginate($this->entityManager->getRepository($input[0]),$input[1],[]);
        $where = $this->paginateService->getWhere();

        $this->assertArraysAreEqual($expectedResult, $where);
    }

    public static function providerPaginate()
    {
        $auditlogWithDateTime = new AuditLogSearchDTO();
        $auditlogWithDateTime->startPeriod = new DateTimeImmutable("2026-10-10");
        $auditlogWithActor = new AuditLogSearchDTO();
        $auditlogWithActor->actor = new Account();
        return array(
            "audditLog" => [
                [
                    [
                        [
                            null,
                            '>',
                            'q',
                            'createdAt'
                        ],
                        [
                            null,
                            '<',
                            'q',
                            'createdAt'
                        ],
                        [
                            null,
                            '=',
                            'Account',
                            'id'
                        ],
                    ],
                    [
                        'actorAccount' => 'Account',
                    ],
                ],
            [AuditLog::class,new AuditLogSearchDTO()]],
            "auditlog With filled datetime should convert to string" =>  [
                [
                    [
                        [
                            '2026-10-10T00:00:00+00:00',
                            '>',
                            'q',
                            'createdAt'
                        ],
                        [
                            null,
                            '<',
                            'q',
                            'createdAt'
                        ],
                        [
                            null,
                            '=',
                            'Account',
                            'id'
                        ],
                    ],
                    [
                        'actorAccount' => 'Account',
                    ],
            ],[AuditLog::class,$auditlogWithDateTime]],
            "auditlog With filled actor should convert to string of id" =>  [
                [
                    [
                        [
                            null,
                            '>',
                            'q',
                            'createdAt'
                        ],
                        [
                            null,
                            '<',
                            'q',
                            'createdAt'
                        ],
                        [
                            $auditlogWithActor->actor->getId(),
                            '=',
                            'Account',
                            'id'
                        ],
                    ],
                    [
                        'actorAccount' => 'Account',
                    ],
            ],[AuditLog::class,$auditlogWithActor]],
            "systemLog" =>[
                [
                    [
                        [
                            null,
                            '>',
                            'q',
                            'createdAt'
                        ],
                        [
                            null,
                            '<',
                            'q',
                            'createdAt'
                        ],
                        [
                            null,
                            '=',
                            'q',
                            'level'
                        ],
                    ],
                    [],
                ],
            [SystemLog::class,new SystemLogSearchDTO()]]
        );
    }

    //getPage

    #[DataProvider('providerGetPageLink')]
    public function testWhenGetingPageWithLinkShouldReturnCorrectPage(array $expectedResult ,array $input): void
    {
        $this->paginateService->setMode($input['4']);
        $this->paginateService->paginate($this->entityManager->getRepository($input[0]),$input[1],['id' => 'ASC','createdAt'=>'ASC'],$input[3]);

        $result = $this->paginateService->getPage();
        if($input[2] != null)
        {
            for($i = 0 ; $i < count($input[2]);$i++)
            {
                $link = $this->paginateService->getLinks()[$input[2][$i]];
                $result = $this->paginateService->getPage($link);
            }
        }

        $this->assertEquals(count($expectedResult),count($result));

        for($i = 0 ; $i < count($result);$i++)
        {
            $this->assertEquals($expectedResult[$i],$result[$i]->getAction());
        }
    }

    public static function providerGetPageLink()
    {
        return array(
            "When in CursorMode link is empty should return first results" =>
            [['login','test'],[AuditLog::class,new AuditLogSearchDTO(),null,2,PaginateAdapterMode::CursorMode]],
            "When in CursorMode link is next page should return second page" =>
            [['test2','test3'],[AuditLog::class,new AuditLogSearchDTO(),['next'],2,PaginateAdapterMode::CursorMode]],
            "When in CursorMode link is 3rd page should return 3rd page" =>
            [['test4','test5'],[AuditLog::class,new AuditLogSearchDTO(),['next','next'],2,PaginateAdapterMode::CursorMode]],
            "When in CursorMode going back to first page should return first page" =>
            [['login','test'],[AuditLog::class,new AuditLogSearchDTO(),['next','prev'],2,PaginateAdapterMode::CursorMode]],
            "When in OffsetMode link is empty should return first results" =>
            [['login','test'],[AuditLog::class,new AuditLogSearchDTO(),null,2,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode link is next page should return second page" =>
            [['test2','test3'],[AuditLog::class,new AuditLogSearchDTO(),['next'],2,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode link is 3rd page should return 3rd page" =>
            [['test4','test5'],[AuditLog::class,new AuditLogSearchDTO(),['next','next'],2,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode going back to first page should return first page" =>
            [['login','test'],[AuditLog::class,new AuditLogSearchDTO(),['next','prev'],2,PaginateAdapterMode::OffsetMode]],
        );
    }


    // getLinks
    #[DataProvider('providerGetLinks')]
    public function testWhenGetLinksShouldReturnCorrectLinks(array $expectedResult ,array $input): void
    {
        $this->paginateService->setMode($input[4]);
        $this->paginateService->paginate($this->entityManager->getRepository($input[0]),$input[1],['id' => 'ASC','createdAt'=>'ASC'],$input[3]);

        $result = $this->paginateService->getPage();
        if($input[2] != null)
        {
            for($i = 0 ; $i < count($input[2]);$i++)
            {
                $link = $this->paginateService->getLinks()[$input[2][$i]];
                $result = $this->paginateService->getPage($link);
            }
        }
        if($input[4] == PaginateAdapterMode::CursorMode)
        {
            foreach($expectedResult as $link => $value)
            {
                if($value != null)
                {
                    $expectedResult[$link] =  $value[0].$result[$value[1]]->getId();
                }
            }
        }

        $this->assertEquals($expectedResult,$this->paginateService->getLinks());
    }

    public static function providerGetLinks()
    {
        return array(
            "When in CursorMode getting first page should have only next link" =>
            [['next'=> ['forward=true&id=',19],'prev' => null],[AuditLog::class,new AuditLogSearchDTO(),null,20,PaginateAdapterMode::CursorMode]],
            "When in CursorMode getting second page should have no next link and have previous link" =>
            [['next'=> null,'prev' => ['forward=false&id=',0]],[AuditLog::class,new AuditLogSearchDTO(),['next'],20,PaginateAdapterMode::CursorMode]],
            "When in CursorMode returning to first page should have next link and have no previous link" =>
            [['next'=> ['forward=true&id=',19],'prev' => null],[AuditLog::class,new AuditLogSearchDTO(),['next','prev'],20,PaginateAdapterMode::CursorMode]],
            "When in CursorMode getting second page with low limit should have next link and have previous link" =>
            [['next'=> ['forward=true&id=',1],'prev' => ['forward=false&id=',0]],[AuditLog::class,new AuditLogSearchDTO(),['next'],2,PaginateAdapterMode::CursorMode]],
            "When in OffsetMode getting first page should have only next link" =>
            [['next'=> 'page=2','prev' => null, 2 =>'page=2'],[AuditLog::class,new AuditLogSearchDTO(),null,20,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode getting second page should have no next link and have previous link" =>
            [['next'=> null,'prev' => 'page=1', 1 =>'page=1'],[AuditLog::class,new AuditLogSearchDTO(),['next'],20,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode returning to first page should have next link and have no previous link" =>
            [['next'=> 'page=2','prev' => null, 2 =>'page=2'],[AuditLog::class,new AuditLogSearchDTO(),['next','prev'],20,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode getting second page with low limit should have next link and have previous link" =>
            [['next'=> 'page=3','prev' => 'page=1', 1 =>'page=1', 3 =>'page=3', 4 =>'page=4', 5 =>'page=5', 6 =>'page=6', 7 =>'page=7', 8 =>'page=8', 9 =>'page=9', 10 =>'page=10', 11 =>'page=11']
            ,[AuditLog::class,new AuditLogSearchDTO(),['next'],2,PaginateAdapterMode::OffsetMode]],
        );
    }


    public function testWhenPaginateNotCalledBeforeGetPageShouldGiveLogicException(): void
    {
        try{
         $result = $this->paginateService->getPage();
        }
        catch(LogicException $e)
        {
            $this->assertEquals("PaginateService is not initialized call the method 'paginate' first",$e->getMessage());
        }        
    }

    // hasPrevPage && hasNextPage

    #[DataProvider('providerHasPage')]
    public function testWhenHasPageShouldReturnCorrectResult(array $expectedResult ,array $input): void
    {
         $this->paginateService->setMode($input[4]);
        $this->paginateService->paginate($this->entityManager->getRepository($input[0]),$input[1],['id' => 'ASC','createdAt'=>'ASC'],$input[3]);

        $this->paginateService->getPage();
        if($input[2] != null)
        {
            for($i = 0 ; $i < count($input[2]);$i++)
            {
                $link = $this->paginateService->getLinks()[$input[2][$i]];
                $this->paginateService->getPage($link);
            }
        }

        $this->assertEquals($expectedResult[0],$this->paginateService->hasPrevPage());
        $this->assertEquals($expectedResult[1],$this->paginateService->hasNextPage());
    }

    public static function providerHasPage()
    {
        return array(
            "When in CursorMode getting first page should have next page and no previous page" =>
            [[false,true],[AuditLog::class,new AuditLogSearchDTO(),null,20,PaginateAdapterMode::CursorMode]],
            "When in CursorMode getting second page should have no next page and have previous page" =>
            [[true,false],[AuditLog::class,new AuditLogSearchDTO(),['next'],20,PaginateAdapterMode::CursorMode]],
            "When in CursorMode returnning to first pageshould have next page and have no previous page" =>
            [[false,true],[AuditLog::class,new AuditLogSearchDTO(),['next','prev'],20,PaginateAdapterMode::CursorMode]],
            "When in CursorMode getting second page with low limit should have next page and have previous page" =>
            [[true,true],[AuditLog::class,new AuditLogSearchDTO(),['next'],2,PaginateAdapterMode::CursorMode]],
            "When in OffsetMode getting first page should have next page and no previous page" =>
            [[false,true],[AuditLog::class,new AuditLogSearchDTO(),null,20,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode getting second page should have no next page and have previous page" =>
            [[true,false],[AuditLog::class,new AuditLogSearchDTO(),['next'],20,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode returnning to first pageshould have next page and have no previous page" =>
            [[false,true],[AuditLog::class,new AuditLogSearchDTO(),['next','prev'],20,PaginateAdapterMode::OffsetMode]],
            "When in OffsetMode getting second page with low limit should have next page and have previous page" =>
            [[true,true],[AuditLog::class,new AuditLogSearchDTO(),['next'],2,PaginateAdapterMode::OffsetMode]],
        );
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->paginateService = null;
        $this->entityManager = null;
    }
}


class AuditLogMutpleWheresDTOTest{

   #[Paginate\Where('createdAt','>')]
   public ?DateTimeImmutable $startPeriod = null;   

   #[Paginate\Where('createdAt','<')]
   public ?DateTimeImmutable $endPeriod = null;

   #[Paginate\Join('Account','actorAccount')]
   public ?Account $actor  = null;

}
