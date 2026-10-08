<?php
namespace App\Twig\Components\Admin;

use App\Entity\Log\SystemLog;
use App\Form\Admin\SystemLogSearchType;
use App\Module\Admin\DTO\SystemLogSearchDTO;
use App\Repository\Log\SystemLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveAction;

#[AsLiveComponent]
class SystemLogSearch extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    
    public array $systemLogs = [];
    
    #[LiveProp]
    public ?SystemLog $cursorStart = null;

    #[LiveProp]
    public ?SystemLog $cursorEnd = null;

    #[LiveProp]
    public bool $hasNext = false;

    #[LiveProp]
    public bool $hasPrev = false;

     #[LiveProp]
    public bool $hasSearched = false;

    #[LiveProp]
    public ?SystemLogSearchDTO $formData = null;


    public function __construct()
    {
    }

    protected function instantiateForm(): FormInterface
    {
        if($this->formData == null)
        {
            $this->formData = new SystemLogSearchDTO();
        }
        return $this->createForm(SystemLogSearchType::class, $this->formData);
    }

    #[LiveAction]
    public function search(SystemLogRepository $systemLogRepository)
    {        
        $this->submitForm();
        $form = $this->getForm();
        if($form->isValid())
        {
            $data = $this->getForm()->getData();
            $this->systemLogs = $systemLogRepository->findByCursor(null,$data->level,$data->startPeriod,$data->endPeriod); 
            if(count($this->systemLogs) > 0)
            {
                $this->cursorStart = null;
                $this->hasPrev = false;
                $this->cursorEnd = end($this->systemLogs);
                $this->hasNext = $systemLogRepository->hasNext($this->cursorEnd,$this->formData->level,$this->formData->startPeriod,$this->formData->endPeriod);
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
    public function prev(SystemLogRepository $systemLogRepository)
    {        
        if($this->hasPrev)
        {
            $this->systemLogs = $systemLogRepository->findByCursor($this->cursorStart,$this->formData->level,$this->formData->startPeriod,$this->formData->endPeriod,false); 
            $this->hasNext = true; 
            $this->cursorEnd = end($this->systemLogs);
            $this->hasPrev = $systemLogRepository->hasPrev($this->systemLogs[0],$this->formData->level,$this->formData->startPeriod,$this->formData->endPeriod); 
            $this->cursorStart = $this->systemLogs[0];
        }
    }

    #[LiveAction]
    public function next(SystemLogRepository $systemLogRepository)
    {        
        if($this->hasNext)
        {
            $this->systemLogs = $systemLogRepository->findByCursor($this->cursorEnd,$this->formData->level,$this->formData->startPeriod,$this->formData->endPeriod); 
            $this->hasNext = $systemLogRepository->hasNext(end($this->systemLogs),$this->formData->level,$this->formData->startPeriod,$this->formData->endPeriod);
            $this->cursorEnd = end($this->systemLogs); 
            $this->hasPrev = true;      
            $this->cursorStart = $this->systemLogs[0];   
        }
    }
}