<?php

namespace App\Twig\Components\Org;

use App\Repository\Account\ProfileRepository;
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

    public function __construct(readonly private ProfileRepository $profileRepository)
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
            $this->selectedProfileIds = array_diff($this->selectedProfileIds, [$id]);
        }
    }

    public function getProfiles(): array
    {
        if (mb_strlen($this->query) < 2) {
            return [];
        }
        return $this->profileRepository->search($this->query);
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

    public function inviteSelectedProfiles()
    {
        // send invites to profiles
    }


}
