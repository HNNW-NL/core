<?php

namespace App\Entity\Admin;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'admins')]
#[ORM\HasLifecycleCallbacks]
class Admin
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(inversedBy: 'admin')]
    #[ORM\JoinColumn(name: 'account_id', unique: true, nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(inversedBy: 'adminRoleMembers')]
    #[ORM\JoinColumn(name: 'admin_role_id', nullable: false)]
    private AdminRole $adminRole;

    #[ORM\ManyToOne(inversedBy: 'admins')]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private Status $status;

    #[ORM\Column(name: 'is_super_admin', type: "boolean")]
    private bool $isSuperAdmin = false;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


    // Reverse FKs

    /* Insert reverse FKs here */


    // Functions

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }

    public function makeSuperAdmin(): void
    {
        $this->isSuperAdmin = true;
    }

    public function revokeSuperAdmin(): void
    {
        $this->isSuperAdmin = false;
    }


    /// Getters & Setters Functions

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getAdminRole(): AdminRole
    {
        return $this->adminRole;
    }

    public function setAdminRole(AdminRole $adminRole): static
    {
        $this->adminRole = $adminRole;

        return $this;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }
}
