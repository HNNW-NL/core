<?php

namespace App\Twig\Components\Org;

use App\Repository\Account\ProfileRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Entity\Account\Profile;
use App\Module\Org\Service\InviteParticipantsService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class InviteParticipantsSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public array $selectedProfileIds = [];

    #[LiveProp(writable: true)]
    public string $projectId = '';

    public function __construct(readonly private ProfileRepository $profileRepository,
                                readonly private ProjectParticipantRepository $projectParticipantRepository
                                )
    {
    }

    #[LiveAction]
    public function selectProfile(#[LiveArg] string $id): void
    {
        if (!in_array($id, $this->selectedProfileIds, true)) {
            $this->selectedProfileIds[] = $id;
        }
        else
        {
            $this->removeSelectedProfile($id);
        }
    }

    #[LiveAction]
    public function removeSelectedProfile(#[LiveArg] string $id): void
    {
        $this->selectedProfileIds = array_diff($this->selectedProfileIds, [$id]);
    }

    #[LiveAction]
    public function inviteSelectedProfiles(InviteParticipantsService $inviteParticipantsService): void
    {
        $inviteParticipantsService->addToProject($this->projectId, $this->getSelectedProfiles());
        $this->selectedProfileIds = [];
    }

    public function getProfiles(): array
    {
        if (mb_strlen($this->query) < 2) {
            return [];
        }

        return $this->profileRepository->searchExcluding($this->query, $this->getProfileIdsInProject());
    }

    public function getSelectedProfiles(): array
    {
        if (!$this->selectedProfileIds) {
            return [];
        }

        return $this->profileRepository->findBy([
            'id' => $this->selectedProfileIds,
        ]);
    }

    public function getParticipants(): array
    {
        return $this->projectParticipantRepository->findBy(['project' => $this->projectId]);
    }

    public function getProfilesInProject(): array
    {
        return array_map(fn ($participant) => $participant->getProfile(), $this->getParticipants());
    }

    public function getProfileIdsInProject(): array
    {
        return array_map(fn(Profile $profile) => $profile->getId(), $this->getProfilesInProject());
    }


}
