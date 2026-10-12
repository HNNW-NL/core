<?php

namespace App\Twig\Components\Org;

use App\Repository\Project\ProjectRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Entity\Project\Project;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsLiveComponent]
class ModifyTaskComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $projectId = '';


    public function __construct(readonly private AuthorizationCheckerInterface $authorizationChecker,
                                readonly private ProjectRepository $projectRepository,
                                readonly private PackageTaskRepository $packageTaskRepository,
    )
    {
    }


    public function getProject(): project
    {
        return $this->projectRepository->find($this->projectId);
    }

    public function getTasks(): iterable
    {
        return $this->packageTaskRepository->findBy(['project' => $this->projectId]);
    }
}
