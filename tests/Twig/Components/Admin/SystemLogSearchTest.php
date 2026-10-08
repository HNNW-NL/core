<?php

namespace App\Tests\Twig\Components\Admin;

use App\Twig\Components\Admin\SystemLogSearch;
use DateTime;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SystemLogSearchTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testShouldRenderComponent(): void
    {
        $testComponent = $this->createLiveComponent(
            name: SystemLogSearch::class,
        );

        $this->assertStringContainsString('Klik op zoeken om system logs te vinden',$testComponent->render());
    }


    #[DataProvider('provideSearchData')]
    public function testWhenSearchingShouldReturnResults(string $expectedResult ,array $input): void
    {
        $testComponent = $this->createLiveComponent(
            name: SystemLogSearch::class,
        );

        $testComponent->submitForm($input,'search');  

        $this->assertStringContainsString($expectedResult,$testComponent->render());
    }

    public static function provideSearchData()
    {        
        return array(
           "When Empty search Should returns results" => ['A system message',['system_log_search' => [],'search']],
           "When Startdate today Should return no results" => ['Geen system logs gevonden',['system_log_search'=>['startPeriod' => new DateTimeImmutable()->format(DateTime::RFC3339)]]],
          );
    }

    public function testWhenEndateBeforeStartDateShouldReturnValidationError(): void
    {
        $testComponent = $this->createLiveComponent(
            name: SystemLogSearch::class,
        );
         $input = ['system_log_search'=>['startPeriod' => new DateTimeImmutable()->format(DateTime::RFC3339),'endPeriod' => new DateTimeImmutable('now')->modify('-1 days')->format(DateTime::RFC3339)]];
        
        try{       
            $testComponent->submitForm($input,'search');  
        }catch(UnprocessableEntityHttpException $e){
            $this->assertStringContainsString('De einddatum moet na of hetzelfde als de startdatum zijn',$e->getMessage());
        }
    }

    public function testWhenLevelWithNoLogsSearchedShouldReturnNoResults(): void
    {
        $testComponent = $this->createLiveComponent(
            name: SystemLogSearch::class,
        );

        $input = ['system_log_search'=>['level' => 'level5']];
              
        $testComponent->submitForm($input,'search');  
        
        $this->assertStringContainsString('Geen system logs gevonden',$testComponent->render());
    }

    public function testWhenNoResultsShouldNotHaveNextAndPrev(): void
    {
        $testComponent = $this->createLiveComponent(
            name: SystemLogSearch::class,
        );

        $input = ['system_log_search'=>['level' => 'level5']];
              
        $testComponent->submitForm($input,'search');  
        $component = $testComponent->component();


        $this->assertFalse($component->hasPrev);
        $this->assertFalse($component->hasNext);
    }

    public function testWhenMoreThan20ResultsShouldHaveNextPage(): void
    {
        $testComponent = $this->createLiveComponent(
            name: SystemLogSearch::class,
        );

        $input = ['system_log_search'=>['level' => '1']];
              
        $testComponent->submitForm($input,'search');  
        $component = $testComponent->component();

        $this->assertTrue($component->hasNext);

        $testComponent->call('next');        

        $this->assertStringContainsString('system log 21',$testComponent->render());
    }

    public function testWhenMoreThan20ResultsShouldBeAbleToReturnToFirstPage(): void
    {
        $testComponent = $this->createLiveComponent(
            name: SystemLogSearch::class,
        );

        $input = ['system_log_search'=>['level' => '1']];
              
        $testComponent->submitForm($input,'search');  
        $testComponent->call('next');        
        $component = $testComponent->component();

        $this->assertTrue($component->hasPrev);
        
        $testComponent->call('prev');

        $this->assertStringContainsString('A system message',$testComponent->render());
    }
}
