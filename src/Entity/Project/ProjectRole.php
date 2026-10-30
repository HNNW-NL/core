<?php

namespace App\Entity\Project;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'project_roles', uniqueConstraints: [
    new ORM\UniqueConstraint(
        name: 'uniq_project_role_name',
        columns: ['project_id', 'name']
    )
])]
#[ORM\HasLifecycleCallbacks]
class ProjectRole
{
    // Permissions

    public const PERMISSION_VIEW = 1;   // 001
    public const PERMISSION_EDIT = 2;   // 010
    public const PERMISSION_DELETE = 4; // 100


    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'roles')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(name: 'name', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'permissions_mask', type: 'bigint', nullable: true)]
    private ?int $permissionsMask = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


    // Reverse FKs

    #[ORM\OneToMany(
        targetEntity: ProjectParticipant::class,
        mappedBy: 'role'
    )]
    private Collection $participants;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->participants = new ArrayCollection();
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


    // Permission Functions

    public function hasPermission(int $permission): bool
    {
        $mask = $this->permissionsMask ?? 0;

        return ($mask & $permission) === $permission;
    }

    public function addPermission(int $permission): static
    {
        $this->permissionsMask = ($this->permissionsMask ?? 0) | $permission;

        return $this;
    }

    public function removePermission(int $permission): static
    {
        $this->permissionsMask = ($this->permissionsMask ?? 0) & ~$permission;

        return $this;
    }


    // Getters & Setters Functions

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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
        return $this->createdAt;
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }

    /** @return Collection<int, ProjectParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }
}