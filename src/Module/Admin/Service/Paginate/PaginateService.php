<?php

namespace App\Module\Admin\Service\Paginate;

use App\Module\Admin\Service\Paginate\Adapters\PaginateAdapterInterface;
use App\Module\Admin\Service\Paginate\Adapters\CursorAdapter;
use App\Module\Admin\Service\Paginate\Adapters\OffsetAdapter;
use App\Module\Admin\Service\Paginate\Adapters\PaginateAdapterMode;
use App\Module\Admin\Service\Paginate\Attributes\Join;
use App\Module\Admin\Service\Paginate\Attributes\Where;
use BadFunctionCallException;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityRepository;
use LogicException;
use ReflectionProperty;
use UnexpectedValueException;

class PaginateService
{

    /**
    * @var ?EntityRepository $repository repository of entity
    */
    private ?EntityRepository $repository = null;

    /**
     * @var int $limit amount of results per page
     */
    private int $limit = 20;
    /**
     * @var array $where multi array of where and join conditions
     */
    private array $where = [[],[]];
    /**
     * @var array $orderBy how results are ordered
     */
    private ?array $orderBy = null;

    private PaginateAdapterInterface $adapter;

    private PaginateAdapterMode $mode;

    private bool $isInitialized = false;

    public function __construct(
    ) {
        $this->adapter = new CursorAdapter();
        $this->mode = PaginateAdapterMode::CursorMode;
    }

    /**
     * @param EntityRepository $repository repository of entity that should be paginate
     * @param mixed $where object which detriments which colums in the database are search
     * @param array $orderBy key column to sort value is direction (asc of desc)
     * @param int $limit amount pof results returned per page
     */
    public function paginate(EntityRepository $repository,mixed $where,array $orderBy,int $limit = 20)
    {
        $this->repository = $repository;
        $this->limit = $limit;
        $this->where = (is_array($where)) ? $where : $this->toWhereArray($where);
        $this->orderBy = $orderBy;
        $this->isInitialized = true;
        $this->adapter->initialize($this->repository,$this->where,$this->orderBy,$this->limit);
    }

    public function setMode(PaginateAdapterMode $mode)
    {
        if($mode == $this->mode)
        {
            return;
        }
        $this->mode = $mode;
        switch ($mode) {
            case PaginateAdapterMode::CursorMode:
                $this->adapter = new CursorAdapter();
                break;
            case PaginateAdapterMode::OffsetMode:
                $this->adapter = new OffsetAdapter();
                break;
            default:
                throw new UnexpectedValueException("Unexpected value of mode");
        }
        if($this->isInitialized)
        {
            $this->adapter->initialize($this->repository,$this->where,$this->orderBy,$this->limit);
        }
        
    }


    /**
     * @param ?string $link link of the paget to return if null returns first page
     */
    public function getPage(?string $link = null) : array
    {
        if(!$this->isInitialized)
        {
            throw new LogicException("PaginateService is not initialized call the method 'paginate' first");
        }        
        return $this->adapter->getPage($link);
    }

    /**
     * returns the where condition used to create the current pagination
     * @return array current in use where condition
     */
    public function getWhere() :array
    {
        return $this->where;
    }

    /**
     * Return if there exist a next page
     * @return bool bool if next page exist
     */
    public function hasNextPage() : bool
    {
        return $this->adapter->hasNextPage();
    }

    /**
     * Returns if there exist a previous page
     * @return bool bool if previous page exist
     */
    public function hasPrevPage() : bool
    {
        return $this->adapter->hasPrevPage();
    }

    /**
     * Returns links to all pages that can be navigated to from last page
     * @return array links of all pages
     */
    public function getLinks(): array
    {
        return $this->adapter->getLinks();
    }

    private function toWhereArray(object $where) : array
    {
        $whereArray = [[],[]];
        $value = null;

        foreach($where as $key => $value)
        {
            $compare = '=';            
            $property = new ReflectionProperty($where::class, $key);
            $alias = 'q';
                       
            $joinAttributes = $property->getAttributes(Join::class);
            if(count($joinAttributes) > 0)
            {
                $arguments = $joinAttributes[0]->getArguments();
                $alias = $arguments[0];
                $whereArray[1][$arguments[1]] = $arguments[0];
                
                if(is_object($value))
                {
                    if(method_exists($value,'getId'))
                    {                        
                        $value = (string) $value->getId();
                    }else{
                        throw new BadFunctionCallException("Expected object to have method 'getId'");
                    }
                }
                array_push($whereArray[0],[$value,'=',$alias,'id']);
            } 

            if(is_object($value))
            {
                if(is_subclass_of($value,DateTimeInterface::class))
                {
                    $value = $value->format(DateTime::RFC3339);
                }else if(method_exists($value,'getId')){
                     $value = $value->getId();
                }else{
                    $value = (string) $value;
                }
            }

            $whereAttributes = $property->getAttributes(Where::class);
            if(count($whereAttributes) > 0)
            {
                $arguments = $whereAttributes[0]->getArguments();
                $key = $arguments[0];
                $compare = $arguments[1];
                array_push($whereArray[0],[$value,$compare,$alias,$key]);                
                
            } else if(count($joinAttributes) == 0){
                array_push($whereArray[0],[$value,$compare,$alias,$key]);
            }            
        }

        return $whereArray;
    }
}