<?php

namespace App\Module\Admin\Service\Paginate\Adapters;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Override;

class OffsetAdapter extends PaginateAdapter{

    private int $maxPages = -1;
    private int $currentPage = 1;

    #[Override]
    public function initialize(EntityRepository $repository,array $where,array $orderBy,int $limit = 20)
    {
        parent::initialize($repository,$where,$orderBy,$limit);
        $this->maxPages = $this->getMaxPages();
    }

    #[Override]
    public function getLinks(int $maxPages = 10): array
    {
        $this->ensureFetch($this->prevLink);

        $links = [];
        $links['prev'] = $this->links['prev'];
        $pagePosition = max($this->currentPage - floor($maxPages/2),1);
        
        for($i = 0; $i < $maxPages; $i++)
        {
            if($pagePosition == $this->currentPage)
            {
                $pagePosition++;
            }
            if($pagePosition > $this->maxPages)
            {
                break;
            }
            $links[$pagePosition] = 'page='.$pagePosition;
            $pagePosition++;
        }
        $links['next'] = $this->links['next'];
        return  $links;
    }
    
    #[Override]
    protected function fetchItems(?string $link = null)
    {
        $this->hasFetched = true;
        $this->prevLink = $link;
        $page = 1;
        if($link != null)
        {
            parse_str($link , $output);
            $page = intval($output['page']);
            if($page == 0)
            {
                throw new InvalidArgumentException('Expected page to be a int');
            }
            $this->currentPage = intval($page);
        }
        
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setFirstResult(($page-1)*$this->limit);
        $qb->setMaxResults($this->limit);

        $this->addWhere($qb);
        $this->addOrder($qb);

        $this->cachedItems = $qb->getQuery()->getResult();

        if(count($this->cachedItems) == $this->limit)
        {
            $this->links['next'] = $this->getNextPageLink($page);
        }else {
            $this->links['next']  = null;
        }
        if(count($this->cachedItems) > 0)
        {
            if($page > 1)
            {
                $this->links['prev'] = $this->getPrevPageLink($page);    
            } else{
                $this->links['prev'] = null;
            }
        }else {
            $this->links['prev']  = null;
        }
    }

    #[Override]
    protected function getNextPageLink(string $page) : ?string
    {
        $page++;
        return ($this->maxPages >= $page) ? "page=".$page: null;
    }

    #[Override]
    protected function getPrevPageLink(string $page) : ?string
    {
        $page--;
        return ($page > 0) ? "page=".$page : null;
    }

    public function getMaxPages() : int
    {
        if($this->maxPages != -1)
        {
            return $this->maxPages;
        }

        $qb = $this->repository->createQueryBuilder('q');

        $this->addWhere($qb);
        $qb->select('COUNT(q.id)');

        $this->maxPages = ceil($qb->getQuery()->getSingleScalarResult()/$this->limit);
        return $this->maxPages;
    }
}