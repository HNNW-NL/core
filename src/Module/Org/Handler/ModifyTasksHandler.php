<?php

namespace App\Module\Org\Handler;

use App\Entity\Account\Account;
use App\Repository\Project\PackageTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ModifyTasksHandler
{
    public function __construct(
        readonly private PackageTaskRepository $taskRepo,
        readonly private EntityManagerInterface $em
    ) {}
}
