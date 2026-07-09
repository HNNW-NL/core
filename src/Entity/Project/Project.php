<?php
namespace App\Entity\Project;

use App\Entity\Account\Account;
use App\Entity\Account\Review;
use App\Entity\Org\Organisation;
use App\Entity\Common\Status;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'project')]
    private Collection $reviews;

    #[ORM\OneToMany(targetEntity: ProjectRole::class, mappedBy: 'project')]
    private Collection $roles;

    #[ORM\OneToMany(targetEntity: ProjectApplication::class, mappedBy: 'project')]
    private Collection $applications;

    #[ORM\OneToMany(targetEntity: ProjectParticipant::class, mappedBy: 'project')]
    private Collection $participants;

    #[ORM\OneToMany(targetEntity: ProjectUpdate::class, mappedBy: 'project')]
    private Collection $updates;

    #[ORM\OneToMany(targetEntity: WorkPackage::class, mappedBy: 'project')]
    private Collection $workPackages;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();

        $this->reviews = new ArrayCollection();
        $this->roles = new ArrayCollection();
        $this->applications = new ArrayCollection();
        $this->participants = new ArrayCollection();
        $this->updates = new ArrayCollection();
        $this->workPackages = new ArrayCollection();
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

    public function enableRemoteWork(): void
    {
        $this->remotePossible = true;
    }

    public function disableRemoteWork(): void
    {
        $this->remotePossible = false;
    }

    public function publish(): void
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function unpublish(): void
    {
        $this->publishedAt = null;
    }

    public function clearEndDate(): void
    {
        $this->endDate = null;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }


/// Getters & Setters Functions

    public function getOwnerAccount(): ?Account
    {
        return $this->ownerAccount;
    }

    public function setOwnerAccount(?Account $ownerAccount): static
    {
        $this->ownerAccount = $ownerAccount;

        return $this;
    }

    public function getOwnerOrganisation(): ?Organisation
    {
        return $this->ownerOrganisation;
    }

    public function setOwnerOrganisation(?Organisation $ownerOrganisation): static
    {
        $this->ownerOrganisation = $ownerOrganisation;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(?string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

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

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function isRemotePossible(): bool
    {
        return $this->remotePossible;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    /** @return Collection<int, ProjectRole> */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /** @return Collection<int, ProjectApplication> */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    /** @return Collection<int, ProjectParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    /** @return Collection<int, ProjectUpdate> */
    public function getUpdates(): Collection
    {
        return $this->updates;
    }

    /** @return Collection<int, WorkPackage> */
    public function getWorkPackages(): Collection
    {
        return $this->workPackages;
    }
}
