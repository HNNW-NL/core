<?php

namespace App\Twig\Components\Admin;

use App\Form\Admin\NotificationSearchType;
use App\Module\Admin\DTO\NotificationSearchDTO;
use App\Repository\Account\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveAction;

#[AsLiveComponent]
class NotificationSearch extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp]
    public ?NotificationSearchDTO $formData = null;

    public function __construct(private NotificationRepository $notificationRepository)
    {
    }

    protected function instantiateForm(): FormInterface
    {
        if($this->formData == null)
        {
            $this->formData = new NotificationSearchDTO();
        }
        return $this->createForm(NotificationSearchType::class, $this->formData);
    }

    public function getnotifications(): array
    {
        return $this->notificationRepository->findLatestChanged();
    }

    #[LiveAction]
    public function search(EntityManagerInterface $entityManager)
    {
        $this->submitForm();
        $post = $this->getForm()->getData();


    }
}