<?php

namespace App\Module\Admin\Service\Paginate\Adapters;

use Doctrine\ORM\QueryBuilder;
use Override;

class CursorAdapter extends PaginateAdapter{

    #[Override]
    protected function fetchItems(?string $link = null)
    {
        $this->hasFetched = true;
        $this->prevLink = $link;
        $cursor = null;
        $isForward = true;
        if($link != null)
        {
            parse_str($link , $output);
            $cursor = $output['id'];
            $isForward = $output['forward'] == 'true';
        }
        
        $qb = $this->repository->createQueryBuilder('q');
        $qb->setMaxResults($this->limit);

        if($cursor)
        {
            $compare = $isForward ? '>' : '<'; 
            $qb->andWhere('q.id '.$compare.' :cursorId')->setParameter('cursorId',$cursor);
        }

        $this->addWhere($qb);
        $this->addOrder($qb,!$isForward);

        $this->cachedItems = $qb->getQuery()->getResult();

        if(!$isForward)
        {
            $qb = $this->repository->createQueryBuilder('q');
            $ids = array_map(fn($value) : string => (string)$value->getId(),$this->cachedItems);
            $this->addOrder($qb);
            $qb->where('q.id in(:ids)')
                ->setParameter(':ids',$ids);
            $this->cachedItems = $qb->getQuery()->getResult();
        }

        if(count($this->cachedItems) == $this->limit)
        {
            $this->links['next'] = $this->getNextPageLink(end($this->cachedItems)->getId());
        }else {
            $this->links['next']  = null;
        }
        if(count($this->cachedItems) > 0)
        {
            $this->links['prev']  = $cursor == null ? null : $this->getPrevPageLink($this->cachedItems[0]->getId());            
        }else {
            $this->links['prev']  = null;
        }
    }

    #[Override]
    protected function getNextPageLink(string $cursor) : ?string
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

    #[Override]
    protected function getPrevPageLink(string $cursor) : ?string
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
    
    #[Override]
    protected function addOrder(QueryBuilder $qb, bool $inverse = false)
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
}