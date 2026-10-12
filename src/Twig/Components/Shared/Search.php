<?php
namespace App\Twig\Components\Shared;

use App\Entity\Log\AuditLog;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveAction;

#[AsLiveComponent]
class Search extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    
    public array $results = [];
    
    #[LiveProp]
    public ?AuditLogSearchDTO $cursorStart = null;

    #[LiveProp]
    public ?AuditLogSearchDTO $cursorEnd = null;

    #[LiveProp]
    public bool $hasNext = false;

    #[LiveProp]
    public bool $hasPrev = false;

    #[LiveProp]
    public bool $hasSearched = false;

    public ?object $formData = null;

    #[LiveProp]
    public SearchDTO $searchDTO;


    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    protected function instantiateForm(): FormInterface
    {
        if($this->formData == null)
        {
            $this->formData = new $this->searchDTO->dto;
        }
        return $this->createForm($this->searchDTO->form, $this->formData);
    }

    #[LiveAction]
    public function search()
    {        
        $this->submitForm();
        $form = $this->getForm();
        if($form->isValid())
        {
            $data = $this->getForm()->getData();
            $this->results =$this->getRepository()->findByCursor(null,$data->actor,$data->startPeriod,$data->endPeriod);  
            if(count($this->results) > 0)
            {
                $this->cursorStart = null;
                $this->hasPrev = false;
                $this->cursorEnd = end($this->results);
                $this->hasNext = $this->getRepository()->hasNext($this->cursorEnd,$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod);
            }  else{
                $this->hasPrev = false;
                $this->hasNext = false;
                $this->cursorStart = null;
                $this->cursorEnd = null;
            }
            $this->hasSearched = true;
        }
        
    }

    #[LiveAction]
    public function prev()
    {        
        $repository = $this->getRepository();
        if($this->hasPrev)
        {
            $this->results = $repository->findByCursor($this->cursorStart,$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod,false); 
            $this->hasNext = true; 
            $this->cursorEnd = end($this->results);
            $this->hasPrev = $repository->hasPrev($this->results[0],$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod); 
            $this->cursorStart = $this->results[0];
        }
    }

    #[LiveAction]
    public function next()
    {        
        $repository = $this->getRepository();
        if($this->hasNext)
        {
            $this->results = $repository->findByCursor($this->cursorEnd,$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod); 
            $this->hasNext = $repository->hasNext(end($this->results),$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod);
            $this->cursorEnd = end($this->results); 
            $this->hasPrev = true;      
            $this->cursorStart = $this->results[0];   
        }
    }


    private function getRepository()
    {
        return $this->entityManager->getRepository($this->searchDTO->entity);
    }
}