<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Security\ProjectPermission;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class CreateProjectService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly ProjectLifecycle $lifecycle,
    ) {}

    /** @return array<string, Account> */
    public function projectLeads(Organisation $organisation): array
    {
        $accounts = [$organisation->getOwnerAccount()];
        foreach ($organisation->getOrgMembers() as $member) {
            $accounts[] = $member->getAccount();
        }

        $leads = [];
        foreach ($accounts as $account) {
            if ($account?->getProfile() !== null && $account->getDeletedAt() === null) {
                $leads[(string) $account->getId()] = $account;
            }
        }

        return $leads;
    }

    public function create(Organisation $organisation, array $input): Project
    {
        $title = self::text($input, 'name');
        $summary = self::text($input, 'summary');
        $description = self::text($input, 'description');
        $lead = $this->projectLeads($organisation)[self::text($input, 'project_lead')] ?? null;
        $slug = $this->slugger->slug($title)->lower()->toString();
        $start = self::date($input, 'start_date') ?? new \DateTimeImmutable('today');
        $end = self::date($input, 'end_date');
        $visibility = self::text($input, 'visibility');
        $capacityInput = self::text($input, 'capacity');
        $capacity = $capacityInput === '' ? null : filter_var($capacityInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);

        if ($title === '' || mb_strlen($title) > 120 || mb_strlen($summary) < 5 || mb_strlen($summary) > 280
            || mb_strlen($description) < 10 || mb_strlen($description) > 500 || $slug === '' || strlen($slug) > 350) {
            throw new \InvalidArgumentException('Enter a valid title, summary and description.');
        }
        if ($lead === null) {
            throw new \InvalidArgumentException('Select a project lead from this organisation.');
        }
        if ($end !== null && $end < $start) {
            throw new \InvalidArgumentException('The end date must be on or after the start date.');
        }
        if (!in_array($visibility, ['public', 'private', 'unlisted'], true) || $capacity === false) {
            throw new \InvalidArgumentException('Select a valid visibility and capacity.');
        }
        if ($this->entityManager->getRepository(Project::class)->findOneBy(['slug' => $slug]) !== null) {
            throw new \InvalidArgumentException('A project with this title already exists.');
        }

        $project = (new Project())
            ->setTitle($title)
            ->setSummary($summary)
            ->setDescription($description)
            ->setSlug($slug)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setVisibility($visibility)
            ->setCapacity($capacity)
            ->setStatus($this->lifecycle->initialStatus())
            ->setOwnerOrganisation($organisation)
            ->setOwnerAccount($lead);

        if (self::text($input, 'remote_possible') !== '1') {
            $project->disableRemoteWork();
        }

        $role = (new ProjectRole())->setProject($project)->setName('Project lead')
            ->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::EDIT->value | ProjectPermission::INVITE->value);
        $participantStatus = $this->entityManager->getRepository(Status::class)
            ->findOneBy(['scope' => 'ProjectParticipant', 'name' => 'active']);
        if (!$participantStatus instanceof Status) {
            throw new \InvalidArgumentException('Project creation is unavailable until the active participant status is configured.');
        }
        $participant = (new ProjectParticipant())->setProject($project)->setProfile($lead->getProfile())
            ->setRole($role)->setStatus($participantStatus);
        $participant->join();

        try {
            $this->entityManager->persist($project);
            $this->entityManager->persist($role);
            $this->entityManager->persist($participant);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new \InvalidArgumentException('A project with this title already exists.', previous: $e);
        }

        return $project;
    }

    private static function text(array $input, string $field): string
    {
        $value = $input[$field] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private static function date(array $input, string $field): ?\DateTimeImmutable
    {
        $value = self::text($input, $field);
        if ($value === '' && !is_array($input[$field] ?? null)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Enter a valid date.');
        }

        return $date;
    }
}
