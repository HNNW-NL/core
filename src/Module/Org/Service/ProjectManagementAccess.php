<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\Permission;
use App\Security\ProjectPermission;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\SecurityBundle\Security;

final class ProjectManagementAccess
{
    public function __construct(
        private readonly Security $security,
        private readonly ProjectParticipantRepository $participants,
    ) {}

    /** @return array<string, Organisation> */
    public function organisations(): array
    {
        $account = $this->security->getUser();
        if (!$account instanceof Account) {
            return [];
        }

        $organisations = [];
        $candidates = $account->getOwnedOrganisations()->toArray();
        foreach ($account->getOrgMemberships() as $membership) {
            $candidates[] = $membership->getOrganisation();
        }

        foreach ($candidates as $organisation) {
            if ($organisation !== null && $organisation->getDeletedAt() === null) {
                $organisations[(string) $organisation->getId()] = $organisation;
            }
        }

        return $organisations;
    }

    /** @return array<string, Organisation> */
    public function manageableOrganisations(): array
    {
        return array_filter($this->organisations(), fn (Organisation $organisation): bool =>
            $this->security->isGranted(Permission::ORG_MANAGE_PROJECTS->name, $organisation));
    }

    /** @return array<string, Organisation> */
    public function creationOrganisations(): array
    {
        $account = $this->security->getUser();

        return $account instanceof Account && $account->getProfile() !== null
            ? $this->manageableOrganisations()
            : [];
    }

    public function canEdit(Project $project): bool
    {
        $organisation = $project->getOwnerOrganisation();
        if ($project->getDeletedAt() !== null || $organisation === null || $organisation->getDeletedAt() !== null
            || !$this->security->isGranted(ProjectVoter::EDIT, $project)) {
            return false;
        }

        if ($this->security->isGranted(Permission::ORG_MANAGE_PROJECTS->name, $organisation)) {
            return true;
        }

        $account = $this->security->getUser();
        $profile = $account instanceof Account ? $account->getProfile() : null;
        if ($profile === null) {
            return false;
        }

        $participant = $this->participants->findLatestActiveParticipantByProjectAndProfile($project, $profile);

        return ProjectPermission::EDIT->isGrantedBy($participant?->getRole(), $project);
    }

    public function canDelete(Project $project): bool
    {
        return $this->canEdit($project)
            && $this->security->isGranted(Permission::ORG_MANAGE_PROJECTS->name, $project->getOwnerOrganisation())
            && $this->security->isGranted(ProjectVoter::DELETE, $project);
    }
}
