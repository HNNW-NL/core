<?php
namespace App\Entity\Admin;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'admin_roles')]
#[ORM\HasLifecycleCallbacks]
class AdminRole
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'name', length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'permissions_mask', type: "bigint", nullable: true)]
    private ?int $permissionsMask = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


    // Reverse FKs

    #[ORM\OneToMany(mappedBy: "adminRole", targetEntity: Admin::class)]
    private Collection $adminRoleMembers;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();

        $this->adminRoleMembers = new ArrayCollection();
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
}
