<?php
namespace App\Entity\Project;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Common\Status;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'projects')]
#[ORM\HasLifecycleCallbacks]
class Project
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "ownedProjects")]
    #[ORM\JoinColumn(name: 'owner_account_id', nullable: false)]
    private ?Account $ownerAccount = null;

    #[ORM\ManyToOne(inversedBy: "ownedProjects")]
    #[ORM\JoinColumn(name: 'owner_org_id', nullable: false)]
    private ?Organisation $ownerOrganisation = null;

    #[ORM\ManyToOne(inversedBy: "projects")]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $status = null;

    #[ORM\Column(name: 'visibility', length: 25)]
    private ?string $visibility = null;

    #[ORM\Column(name: 'title', length: 255)]
    private ?string $title = null;

    #[ORM\Column(name: 'slug', length: 350, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(name: 'summary', length: 500)]
    private ?string $summary = null;

    #[ORM\Column(name: 'description', type: 'text')]
    private ?string $description = null;

    #[ORM\Column(name: 'start_date', type: 'datetime_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'end_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(name: 'capacity', type: 'integer')]
    private ?int $capacity = null;

    #[ORM\Column(name: 'remote_possible', type: "boolean", nullable: false)]
    private bool $remotePossible = true;

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;


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

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
}
