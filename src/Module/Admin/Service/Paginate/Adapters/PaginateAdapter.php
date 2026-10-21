<?php

namespace App\Module\Admin\Service\Paginate\Adapters;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use LogicException;

abstract class PaginateAdapter implements PaginateAdapterInterface{

    protected array $links = ['next' => null, 'prev' => null];
    /**
     * @var bool $hasFetched if results have already been fetched
     */
    protected bool $hasFetched = false;

    /**
     * @var array $cachedItems fetched results
     */
    protected ?array $cachedItems = null;
    /**
     * @var ?string $prevLink link used in previous request
     */
    protected ?string $prevLink = '';    

    /**
     * @var array $where multi array of where and join conditions
     */
    protected array $where = [[],[]];
    /**
     * @var array $orderBy how results are ordered
     */
    protected ?array $orderBy = null;

    /**
    * @var ?EntityRepository $repository repository of entity
    */
    protected ?EntityRepository $repository = null;

    /**
     * @var int $limit amount of results per page
     */
    protected int $limit = 20;

    protected bool $isInitialized = false;

   
    public function initialize(EntityRepository $repository,array $where,array $orderBy,int $limit = 20)
    {
        $this->repository = $repository;
        $this->limit = $limit;
        $this->where = $where;
        $this->orderBy = $orderBy;
        $this->isInitialized = true;
    }
    
    public function getLinks(int $maxPages = 10): array
    {
        $this->ensureFetch($this->prevLink);
        return $this->links;
    }

    public function hasNextPage() : bool
    {
        $this->ensureFetch($this->prevLink);
        return $this->links['next'] != null;
    }

    public function hasPrevPage() : bool
    {
        $this->ensureFetch($this->prevLink);
        return $this->links['prev'] != null;
    }

    public function getPage(?string $link = null) : array
    {
        if(!$this->isInitialized)
        {
            throw new LogicException("CursorAdapter is not initialized call the method 'initialize' first");
        }        
        $this->ensureFetch($link);
        return $this->cachedItems;        
    }

    protected function ensureFetch(?string $link = null)
    {
        if($this->hasFetched && $link == $this->prevLink)
        {
            return;
        }
        $this->fetchItems($link);
    }


    protected abstract function fetchItems(?string $link = null);


    protected function getNextPageLink(string $link) : ?string
    {
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setMaxResults(1);

        $this->addWhere($qb);
        $this->addOrder($qb);

        $results = $qb->getQuery()->getResult();
        return (count($results) > 0) ? "page=".$link: null;
    }

    protected function getPrevPageLink(string $link) : ?string
    {
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setMaxResults(1);

        $this->addWhere($qb);
        $this->addOrder($qb);
        
        $results = $qb->getQuery()->getResult();
        return (count($results) > 0) ?  "page=".$link : null;
    }

    protected function addOrder(QueryBuilder $qb)
    {
        foreach($this->orderBy as $key => $value)
        {
            $qb->addOrderBy('q.'.$key,$value);
        }
    }

    protected function addWhere(QueryBuilder $qb)
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