<?php
namespace App\Twig\Components\Shared;

use App\Module\Admin\Service\Paginate\PaginateService;
use App\Twig\Components\Shared\Search\Label;
use App\Twig\Components\Shared\Search\Path;
use App\Twig\Components\Shared\Search\PathParameter;
use App\Twig\Components\Shared\Search\SearchDTO;
use BadFunctionCallException;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionProperty;
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

    /**
    * @var array $results that is display in the display table
    */ 
    public array $results = [];
    /** 
    * @var array $pathParameters that are passed to links created in the result table
    */ 
    public array $pathParameters = [];
    /**
     * @var array $pathParametersFields of parameters of result that should be saved
     */ 
    #[LiveProp]
    public array $pathParametersFields = [];
    
    /**
    * @var array $links paginate links to all possible pages
    */ 
    #[LiveProp]
    public ?array $links = [];

    /**
    * @var bool $hasSearched if there already has been search once
    */ 
    #[LiveProp]
    public bool $hasSearched = false;

    /**
    * @var array $where paginateService where clause
    */ 
    #[LiveProp(hydrateWith: 'hydrateMultiLevelArray', dehydrateWith: 'dehydrateMultiLevelArray')]
    public array $where = [];

    /**
    * @var ?object $formData inital form data
    */
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
        $displayFields =  get_class_vars($this->searchDTO->displayDTO); 

        $reflectionObject = new ReflectionClass($this->searchDTO->displayDTO);
        $pathParameters = $reflectionObject->getAttributes(PathParameter::class);
        foreach($pathParameters as  $pathParameter)
        {
            $value = $pathParameter->getArguments();
            if(count($value) == 2)
            {
                $this->pathParametersFields[$value[0]] = $value[1]; 
            }else{
                $this->pathParametersFields[$value[0]] = 'get'.ucfirst($value[0]);
            }
            
        }

        foreach($displayFields as $field => $value)
        {
            $property = new ReflectionProperty($this->searchDTO->displayDTO, $field);
            $link = $property->getAttributes(Path::class);
            $linkValue = null;
            $count = count($link);
            if($count > 0)
            {
                $linkValue = $link[0]->getArguments()[0];
            }
            $label = $property->getAttributes(Label::class);
            $count = count($label);
            if($count > 0)
            {
                $arguments = $label[0]->getArguments();
                $this->displayFields[$field] = [$arguments[0],$linkValue];
            }else{
                $this->displayFields[$field] = [$field,$linkValue];
            }
        }
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
            $this->pathParameters = $this->getPathParameters($results);
        }
        
    }

    #[LiveAction]
    public function navigate(#[LiveArg] string $link)
    {  
        $this->paginate->paginate($this->getRepository(),$this->where,$this->searchDTO->orderBy);
        $results = $this->paginate->getPage($link); 
        $this->links = $this->paginate->getLinks();
        $this->results = $this->toDisplayDto($results);
        $this->pathParameters = $this->getPathParameters($results);
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

    private function getPathParameters(array $array)
    {
        $pathParameters = [];
        foreach($array as $object )
        {          
            $parameters = [];
            foreach($this->pathParametersFields as $parameter => $method)  
            {
                if(method_exists($object,$method))
                {
                   $parameters[$parameter] = $object->$method();
                }else
                {
                    throw new BadFunctionCallException("Method '".$method. "' does not exist");
                }
            }
            array_push($pathParameters, $parameters);
        }

        return $pathParameters;
    }

    private function getRepository()
    {
        return $this->entityManager->getRepository($this->searchDTO->entity);
    }
}