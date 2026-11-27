<?php

namespace App\Entity\Admin;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'admin_roles')]
#[ORM\HasLifecycleCallbacks]
class AdminRole
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(name: 'name', length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'permissions_mask', type: "bigint", nullable: true)]
    private ?int $permissionsMask = null;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


    // Reverse FKs

    #[ORM\OneToMany(mappedBy: "adminRole", targetEntity: Admin::class)]
    private Collection $adminRoleMembers;


    // Functions

    public function __construct()
    {
        $this->id = new Ulid();

        $this->adminRoleMembers = new ArrayCollection();
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


    /// Getters & Setters Functions

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPermissionsMask(): ?int
    {
        return $this->permissionsMask;
    }

    public function setPermissionsMask(?int $permissionsMask): static
    {
        $this->permissionsMask = $permissionsMask;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }

    /** @return Collection<int, Admin> */
    public function getAdminRoleMembers(): Collection
    {
        return $this->adminRoleMembers;
    }
}
