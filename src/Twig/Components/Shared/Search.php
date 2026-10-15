<?php
namespace App\Twig\Components\Shared;

use App\Module\Admin\Service\Paginate\PaginateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\UX\LiveComponent\Attribute\LiveArg;

#[AsLiveComponent]
class Search extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    public array $results = [];
    
    #[LiveProp]
    public ?array $links = [];

    #[LiveProp]
    public bool $hasSearched = false;

    #[LiveProp(hydrateWith: 'hydrateMultiLevelArray', dehydrateWith: 'dehydrateMultiLevelArray')]
    public array $where = [];

    public ?object $formData = null;

    #[LiveProp]
    public SearchDTO $searchDTO;

    #[LiveProp]
    public array $displayFields = [];

    public function __construct(private EntityManagerInterface $entityManager, private PaginateService $paginate,
    private ObjectMapperInterface $objectMapper)
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

    #[PostMount]
    public function postMount() : void
    {
        $this->displayFields = get_class_vars($this->searchDTO->displayDTO); 
    }
    

    #[LiveAction]
    public function search()
    {        
        $this->submitForm();
        $form = $this->getForm();
        if($form->isValid())
        {
            $data = $this->getForm()->getData();
            $this->paginate->paginate($this->getRepository(),$data,$this->searchDTO->orderBy);
            $results = $this->paginate->getPage();  
            $this->where = $this->paginate->getWhere();
            $this->links = $this->paginate->getLinks();
            $this->hasSearched = true;
            $this->results = $this->toDisplayDto($results);
        }
        
    }

    #[LiveAction]
    public function navigate(#[LiveArg] string $link)
    {  
        $this->paginate->paginate($this->getRepository(),$this->where,$this->searchDTO->orderBy);
        $results = $this->paginate->getPage($link); 
        $this->links = $this->paginate->getLinks();
        $this->results = $this->toDisplayDto($results);
    }

    public function dehydrateMultiLevelArray(array $where)
    {
        return $where;
    }

    public function hydrateMultiLevelArray(array $data)
    {
        return $data;
    }

    private function toDisplayDto(array $array) : array
    {
        $result = [];
        foreach($array as $object )
        {            
            array_push($result,$this->objectMapper->map($object,$this->searchDTO->displayDTO));
        }

        return $result;
    }

    private function getRepository()
    {
        return $this->entityManager->getRepository($this->searchDTO->entity);
    }
}