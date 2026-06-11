<?php
namespace App\Entity\Org;

use App\Entity\Account\Account;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'org_members', uniqueConstraints: [
    new ORM\UniqueConstraint(name: "uniq_org_member_account", columns: ["org_id", "account_id", "org_role_id"]),
])]
#[ORM\HasLifecycleCallbacks]
class OrgMember
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "orgMembers")]
    #[ORM\JoinColumn(name: 'org_id', nullable: false)]
    private ?Organisation $organisation = null;

    #[ORM\ManyToOne(inversedBy: "orgMemberships")]
    #[ORM\JoinColumn(name: 'account_id', nullable: false)]
    private ?Account $account = null;

    #[ORM\ManyToOne(inversedBy: "orgRoleMembers")]
    #[ORM\JoinColumn(name: 'org_role_id', nullable: false)]
    private ?OrgRole $orgRole = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


    // Reverse FKs

    /* Insert reverse FKs here */


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->lastModified = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }


    /// Getters & Setters Functions

    public function getOrganisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(?Organisation $organisation): static
    {
        $this->organisation = $organisation;

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getOrgRole(): ?OrgRole
    {
        return $this->orgRole;
    }

    public function setOrgRole(?OrgRole $orgRole): static
    {
        $this->orgRole = $orgRole;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }
}
