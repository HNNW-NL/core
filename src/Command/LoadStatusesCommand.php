<?php

namespace App\Command;

use App\Entity\Common\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:load-statuses',
    description: 'Loads default statuses'
)]
class LoadStatusesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statuses = [
            'Idea',
            'Brainstorming',
            'Backlog',
            'Draft',
            'Proposed',
            'Under Review',
            'Approved',
            'Rejected',
            'Planned',
            'Ready',
            'To Do',
            'In Progress',
            'Testing',
            'Completed',
            'Done',
            'Closed',
            'Cancelled',
            'Archived',
        ];

        foreach ($statuses as $name) {
            $status = new Status();
            $status->setName($name);
            $status->setScope('project');
            $status->setColourHex('#3B82F6');

            $this->em->persist($status);
        }

        $this->em->flush();

        $output->writeln('Statuses loaded.');

        return Command::SUCCESS;
    }
}