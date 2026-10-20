<?php
namespace App\Twig\Components\Shared\Search;

class SearchDTO{

    /**
    *    Entity from the database that is paginate
    */
    public string $entity;

    /** 
    * Form class  that is displayed and used to search
    */
    public string $form;

    /**
    *  dto class that is used to by the formtype
    */
    public string $dto;

    /** 
    * dto class that determines what property are displayed in the search result
    */
    public string $displayDTO;

    /**
     * Colums that object are sorted by sorted 
     * key column to sort value is direction (asc of desc)
     */
    public array $orderBy;
}