<?php
namespace App\Entity\Org;

use App\Entity\Common\Status;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'organizations')]
#[ORM\HasLifecycleCallbacks]
class Organization
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "organizations")]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $status = null;

    #[ORM\Column(name: 'name', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'logo_url', type: "text", nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(name: 'slug', length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(name: 'description', type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'location', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'website_url', type: "text", nullable: true)]
    private ?string $websiteUrl = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;


    // Reverse FKs


    #[ORM\OneToMany(mappedBy: "organization", targetEntity: OrgRole::class)]
    private Collection $orgRoles;

    #[ORM\OneToMany(mappedBy: "organization", targetEntity: OrgMember::class)]
    private Collection $orgMembers;


    // Functions


    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->orgRoles = new ArrayCollection();
        $this->orgMembers = new ArrayCollection();
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

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
}
