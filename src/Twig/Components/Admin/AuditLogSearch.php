<?php
namespace App\Twig\Components\Admin;

use App\Entity\Log\AuditLog;
use App\Form\Admin\AuditLogSearchType;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use App\Repository\Log\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveAction;

#[AsLiveComponent]
class AuditLogSearch extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    
    public array $auditLogs = [];
    
    #[LiveProp]
    public ?AuditLog $cursorStart = null;

    #[LiveProp]
    public ?AuditLog $cursorEnd = null;

    #[LiveProp]
    public bool $hasNext = false;

    #[LiveProp]
    public bool $hasPrev = false;

     #[LiveProp]
    public bool $hasSearched = false;

    #[LiveProp]
    public ?AuditLogSearchDTO $formData = null;


    public function __construct(private AuditLogRepository $auditLogRepository)
    {
    }

    protected function instantiateForm(): FormInterface
    {
        if($this->formData == null)
        {
            $this->formData = new AuditLogSearchDTO();
        }
        return $this->createForm(AuditLogSearchType::class, $this->formData);
    }

    #[LiveAction]
    public function search(AuditLogRepository $auditLogRepository)
    {        
        $this->submitForm();
        $form = $this->getForm();
        if($form->isValid())
        {
            $data = $this->getForm()->getData();
            $this->auditLogs = $auditLogRepository->findAuditLogByCursor(null,$data->actor,$data->startPeriod,$data->endPeriod); 
            if(count($this->auditLogs) > 0)
            {
                $this->cursorStart = null;
                $this->hasPrev = false;
                $this->cursorEnd = end($this->auditLogs);
                $this->hasNext = $auditLogRepository->hasNext($this->cursorEnd,$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod);
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
    public function prev(AuditLogRepository $auditLogRepository)
    {        
        if($this->hasPrev)
        {
            $this->auditLogs = $auditLogRepository->findAuditLogByCursor($this->cursorStart,$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod,false); 
            $this->hasNext = true; 
            $this->cursorEnd = end($this->auditLogs);
            $this->hasPrev = $auditLogRepository->hasPrev($this->auditLogs[0],$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod); 
            $this->cursorStart = $this->auditLogs[0];
        }
    }

    #[LiveAction]
    public function next(AuditLogRepository $auditLogRepository)
    {        
        if($this->hasNext)
        {
            $this->auditLogs = $auditLogRepository->findAuditLogByCursor($this->cursorEnd,$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod); 
            $this->hasNext = $auditLogRepository->hasNext(end($this->auditLogs),$this->formData->actor,$this->formData->startPeriod,$this->formData->endPeriod);
            $this->cursorEnd = end($this->auditLogs); 
            $this->hasPrev = true;      
            $this->cursorStart = $this->auditLogs[0];   
        }
    }
}