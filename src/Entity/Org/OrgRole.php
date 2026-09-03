<?php
namespace App\Entity\Org;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'org_roles', uniqueConstraints: [
    new ORM\UniqueConstraint(name: "uniq_org_role_name", columns: ["org_id", "name"])
])]
#[ORM\HasLifecycleCallbacks]
class OrgRole
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "orgRoles")]
    #[ORM\JoinColumn(name: 'org_id', nullable: false)]
    private ?Organisation $organisation = null;

    #[ORM\Column(name: 'name', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'permissions_mask', type: "bigint", nullable: true)]
    private ?int $permissionsMask = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


    // Reverse FKs

    #[ORM\OneToMany(mappedBy: "orgRole", targetEntity: OrgMember::class)]
    private Collection $orgRoleMembers;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();

        $this->orgRoleMembers = new ArrayCollection();
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
        return $this->createdAt;
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }

    /** @return Collection<int, OrgMember> */
    public function getOrgRoleMembers(): Collection
    {
        return $this->orgRoleMembers;
    }
}
