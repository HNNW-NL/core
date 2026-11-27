<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;

final class ProjectLeadEligibility
{
    /** @return array<string, Account> */
    public function projectLeads(Organisation $organisation): array
    {
        $accounts = [$organisation->getOwnerAccount()];
        foreach ($organisation->getOrgMembers() as $member) {
            if ($member->getOrganisation() === $organisation) {
                $accounts[] = $member->getAccount();
            }
        }

        $leads = [];
        foreach ($accounts as $account) {
            $profile = $account?->getProfile();
            if ($profile !== null && $profile->getDeletedAt() === null && $account->getDeletedAt() === null) {
                $leads[(string) $account->getId()] = $account;
            }
        }

        return $leads;
    }
}
