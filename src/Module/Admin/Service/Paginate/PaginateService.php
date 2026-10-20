<?php

namespace App\Module\Admin\Service\Paginate;

use App\Module\Admin\Service\Paginate\Attributes\Join;
use App\Module\Admin\Service\Paginate\Attributes\Where;
use BadFunctionCallException;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use LogicException;
use ReflectionProperty;

class PaginateService
{

    /**
     * @var EntityRepository $repository repository of entity
     */
    private EntityRepository $repository;

    /**
     * @var int $limit amount of results per page
     */
    private int $limit = 20;

    /**
     * @var bool $hasFetched if results have already been fetched
     */
    private bool $hasFetched = false;

    /**
     * @var array $cachedItems fetched results
     */
    private array $cachedItems = [null,null,null];
    /**
     * @var array $where multi array of where and join conditions
     */
    private array $where = [[],[]];
    /**
     * @var array $orderBy how results are ordered
     */
    private ?array $orderBy = null;

    public function __construct(
    ) {}

    /**
     * @param EntityRepository $repository repository of entity that should be paginate
     * @param object $where object which detriments which colums in the database are search
     * @param array $orderBy key column to sort value is direction (asc of desc)
     * @param int $limit amount pof results returned per page
     */
    public function paginate(EntityRepository $repository,mixed $where,array $orderBy,int $limit = 20)
    {
        $this->repository = $repository;
        $this->limit = $limit;
        $this->where = (is_array($where)) ? $where : $this->toWhereArray($where);
        $this->orderBy = $orderBy;
    }


    /**
     * @param ?string $link link of the paget to return if null returns first page
     */
    public function getPage(?string $link = null) : array
    {
        if($link == null)
        {
            $this->ensureFetch();
            return $this->cachedItems[0];
        }      
        parse_str($link , $output);
        $this->fetchItems($output['id'],$output['forward'] == 'true');
        
        return $this->cachedItems[0];
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
        $this->ensureFetch();
        return $this->cachedItems[2] != null;
    }

    /**
     * Returns if there exist a previous page
     * @return bool bool if previous page exist
     */
    public function hasPrevPage() : bool
    {
        $this->ensureFetch();
        return $this->cachedItems[1] != null;
    }

    /**
     * Returns links to all pages that can be navigated to from last page
     * @return array links of all pages
     */
    public function getLinks(): array
    {
        return ['prev' => $this->cachedItems[1],'next' => $this->cachedItems[2]];
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

    private function ensureFetch()
    {
        if($this->hasFetched)
        {
            return;
        }
        $this->fetchItems();
    }

    private function fetchItems(?string $cursor = null, bool $isForward = true)
    {
        if($this->repository == null)
        {
            throw new LogicException("PaginateService is not initialized call the method 'paginate' first");
        }
        $this->hasFetched = true;
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setMaxResults($this->limit);

        if($cursor)
        {
            $compare = $isForward ? '>' : '<'; 
            $qb->andWhere('q.id '.$compare.' :cursorId')->setParameter('cursorId',$cursor);
        }

        $this->addWhere($qb);
        $this->addOrder($qb,!$isForward);

        $this->cachedItems[0] = $qb->getQuery()->getResult();

        if(!$isForward)
        {
            $qb = $this->repository->createQueryBuilder('q');
            $ids = array_map(fn($value) : string => (string)$value->getId(),$this->cachedItems[0]);
            $this->addOrder($qb);
            $qb->where('q.id in(:ids)')
                ->setParameter(':ids',$ids);
            $this->cachedItems[0] = $qb->getQuery()->getResult();
        }

        if(count($this->cachedItems[0]) == $this->limit)
        {
            $this->cachedItems[2] = $this->getNextPageLink(end($this->cachedItems[0])->getId());
        }else {
            $this->cachedItems[2] = null;
        }
        if(count($this->cachedItems[0]) > 0)
        {
            $this->cachedItems[1] = $cursor == null ? null : $this->getPrevPageLink($this->cachedItems[0][0]->getId());            
        }else {
             $this->cachedItems[1] = null;
        }
    }

    private function getNextPageLink(string $cursor) : ?string
    {
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setMaxResults(1);

        if($cursor)
        {
            $qb->andWhere('q.id > :cursorId')->setParameter('cursorId',$cursor);
        }

        $this->addWhere($qb);
        $this->addOrder($qb);

        $results = $qb->getQuery()->getResult();
        return (count($results) > 0) ? "forward=true&id=".$cursor: null;
    }

    private function getPrevPageLink(string $cursor) : ?string
    {
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setMaxResults(1);

        if($cursor)
        {
            $qb->andWhere('q.id < :cursorId')->setParameter('cursorId',$cursor);
        }

        $this->addWhere($qb);
        $this->addOrder($qb,true);
        
        $results = $qb->getQuery()->getResult();
        return (count($results) > 0) ?  "forward=false&id=".$cursor : null;
    }

    private function addOrder(QueryBuilder $qb, bool $inverse = false)
    {
        foreach($this->orderBy as $key => $value)
        {
            if($inverse)
            { 
                $value = strtoupper($value) == 'ASC' ? 'DESC' : 'ASC'; 
            }
            $qb->addOrderBy('q.'.$key,$value);
        }
    }

    private function addWhere(QueryBuilder $qb)
    {
        foreach($this->where[0] as $where)
        {
            
            if($where != null)
            {
                $value = $where[0];
                $compare = $where[1];
                $alias = $where[2];
                $key = $where[3];

                if($value != null)
                {                    
                    $whereQuery = $alias.'.'.$key;
                    $parameter = uniqid('parameter');
                    $qb->andWhere($whereQuery .' '.$compare.' :'. $parameter)
                        ->setParameter($parameter, $value);
                }
            }
        }

        foreach($this->where[1] as $key => $value)
        {
            $qb->join('q.'.$key ,$value);
        }
    }
}