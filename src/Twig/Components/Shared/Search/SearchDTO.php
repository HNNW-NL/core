<?php
namespace App\Twig\Components\Shared\Search;

use App\Module\Admin\Service\Paginate\Adapters\PaginateAdapterMode;

class SearchDTO{

    /**
    *  @var string $entity  Entity from the database that is paginate
    */
    public string $entity;

    /** 
    * @var string $form Form class  that is displayed and used to search
    */
    public string $form;

    /**
    * @var string $dto dto class that is used to by the formtype
    */
    public string $dto;

    /** 
    * @var string $displayDTO dto class that determines what property are displayed in the search result
    */
    public string $displayDTO;

    /**
     * @var array $orderBy Colums that object are sorted by sorted 
     * key column to sort value is direction (asc of desc)
     */
    public array $orderBy;
    
}