<?php

namespace App\Module\Admin\Service\Paginate\Adapters;

use Doctrine\ORM\EntityRepository;

interface PaginateAdapterInterface{
    
    /**
     * Returns links to pages that can be navigated to from last page
     * @var int $maxPages max amount of pages to return does not include previous en next link
     * @return array links of all pages
     */
    function getLinks(int $maxPages = 10):array;

    /**
     * @param ?string $link link of the paget to return if null returns first page
     */
    function getPage(?string $link = null) : array;

    /**
     * Return if there exist a next page
     * @return bool bool if next page exist
     */
    public function hasNextPage() : bool;

    /**
     * Returns if there exist a previous page
     * @return bool bool if previous page exist
     */
    public function hasPrevPage() : bool;

    /**
     * @param EntityRepository $repository repository of entity that should be paginate
     * @param array $where multi array of where and join conditions
     * @param array $orderBy key column to sort value is direction (asc of desc)
     * @param int $limit amount pof results returned per page
     */
    function initialize(EntityRepository $repository,array $where,array $orderBy,int $limit = 20);
}