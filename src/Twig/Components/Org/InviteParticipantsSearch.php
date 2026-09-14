<?php

namespace App\Twig\Components\Org;

use App\Repository\Account\ProfileRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class InviteParticipantsSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(readonly private ProfileRepository $profileRepository)
    {
    }

    public function getProfiles(): array
    {
        dump($this->query);

        if (mb_strlen($this->query) < 2) {
            return [];
        }
        return $this->profileRepository->search($this->query);
    }
}
