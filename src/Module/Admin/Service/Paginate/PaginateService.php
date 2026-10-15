<?php

namespace App\Module\Admin\Service\Paginate;

use App\Module\Admin\Service\Paginate\Attributes\Join;
use App\Module\Admin\Service\Paginate\Attributes\WhereTarget;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use ReflectionProperty;

use function PHPUnit\Framework\isArray;

class PaginateService
{

    private EntityRepository $repository;

    private int $limit = 20;

    private bool $hasFetched = false;

    private array $cachedItems = [null,null,null];
    private array $where = [[],[]];
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

    public function getWhere()
    {
        return $this->where;
    }

    /**
     * Return if there exist a next page
     */
    public function hasNextPage() : bool
    {
        $this->ensureFetch();
        return $this->cachedItems[2] != null;
    }

    /**
     * Return if there exist a previous page
     */
    public function hasPrevPage() : bool
    {
        $this->ensureFetch();
        return $this->cachedItems[1] != null;
    }

    /**
     * 
     */
    public function getLinks(): array
    {
        return ['prev' => $this->cachedItems[1],'next' => $this->cachedItems[2]];
    }

    private function toWhereArray(object $where) : array
    {
        $whereArray = [];

        foreach($where as $key => $value)
        {
            $compare = '=';
            $property = new ReflectionProperty($where::class, $key);
            $whereAttributes = $property->getAttributes(WhereTarget::class);
            $alias = 'q';
            if(count($whereAttributes) > 0)
            {
                $arguments = $whereAttributes[0]->getArguments();
                $key = $arguments[0];
                $compare = $arguments[1];
            }            
            $joinAttributes = $property->getAttributes(Join::class);
            if(count($joinAttributes) > 0)
            {
                $arguments = $joinAttributes[0]->getArguments();
                $alias = $arguments[0];
                $whereArray[1][$arguments[1]] = $arguments[0];
                
                if(is_object($value)  && method_exists($value,'getId') )
                {
                    $value = (string) $value->getId();
                }
            }

            

            $whereArray[0][$key] = [$value,$compare,$alias];
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
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setMaxResults($this->limit);

        if($cursor)
        {
            $compare = $isForward ? '>' : '<'; 
            $qb->andWhere('q.id '.$compare.' :cursorId')->setParameter('cursorId',$cursor);
        }

        $this->addWhere($qb);
        $this->addJoins($qb);
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
        }
        if(count($this->cachedItems[0]) > 0)
        {
            $this->cachedItems[1] = $cursor == null ? null : $this->getPrevPageLink($this->cachedItems[0][0]->getId());            
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
        $this->addJoins($qb);
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
        $this->addJoins($qb);
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

    private function addJoins(QueryBuilder $qb)
    {
        foreach($this->where[1] as $key => $value)
        {
            $qb->join('q.'.$key ,$value);
        }
    }

    private function addWhere(QueryBuilder $qb)
    {
        foreach($this->where[0] as $key => $value)
        {
            if($value[0] != null)
            {
                $compare = $value[1];
                $whereQuery = $value[2].'.'.$key;
                $qb->andWhere($whereQuery .' '.$compare.' :'.$key)
                    ->setParameter($key, $value[0]);
            }
        }
    }

    
}