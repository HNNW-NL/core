<?php

namespace App\Entity\Org;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Common\Event;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\Account;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'organisations')]
#[ORM\HasLifecycleCallbacks]
class Organisation
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: "organisations")]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $status = null;

    #[ORM\ManyToOne(
        targetEntity: Account::class,
        inversedBy: 'ownedOrganisations'
    )]
    #[ORM\JoinColumn(
        name: 'owner_account_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private ?Account $ownerAccount = null;

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

    #[ORM\Column(name: 'sector', length: 120, nullable: true)]
    private ?string $sector = null;

    #[ORM\Column(name: 'contact_email', length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(name: 'verified', type: 'boolean', options: ['default' => false])]
    private bool $verified = false;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;


    // Reverse FKs

    #[ORM\OneToMany(targetEntity: OrgRole::class, mappedBy: "organisation")]
    private Collection $orgRoles;

    #[ORM\OneToMany(targetEntity: OrgMember::class, mappedBy: "organisation")]
    private Collection $orgMembers;

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: "ownerOrganisation")]
    private Collection $ownedProjects;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'organisation')]
    private Collection $events;

    /**
     * @var Collection<int, ProfileExperience>
     */
    #[ORM\OneToMany(
        targetEntity: ProfileExperience::class,
        mappedBy: 'organisation'
    )]
    private Collection $profileExperiences;


    // Functions

    public function __construct()
    {
        $this->id = new Ulid();

        $this->orgRoles = new ArrayCollection();
        $this->orgMembers = new ArrayCollection();
        $this->ownedProjects = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->profileExperiences = new ArrayCollection();
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

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }


    /// Getters & Setters Functions

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): static
    {
        $this->status = $status;

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

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(?string $websiteUrl): static
    {
        $this->websiteUrl = $websiteUrl;

        return $this;
    }

    public function getSector(): ?string
    {
        return $this->sector;
    }

    public function setSector(?string $sector): static
    {
        $this->sector = $sector;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /** @return Collection<int, OrgRole> */
    public function getOrgRoles(): Collection
    {
        return $this->orgRoles;
    }

    /** @return Collection<int, OrgMember> */
    public function getOrgMembers(): Collection
    {
        return $this->orgMembers;
    }

    /** @return Collection<int, Project> */
    public function getOwnedProjects(): Collection
    {
        return $this->ownedProjects;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setOrganisation($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            if ($event->getOrganisation() === $this) {
                $event->setOrganisation(null);
            }
        }

        return $this;
    }

    public function getOwnerAccount(): ?Account
    {
        return $this->ownerAccount;
    }

    public function setOwnerAccount(?Account $ownerAccount): static
    {
        if ($this->ownerAccount === $ownerAccount) {
            return $this;
        }

        $previousOwner = $this->ownerAccount;
        $this->ownerAccount = $ownerAccount;

        if ($previousOwner !== null) {
            $previousOwner->getOwnedOrganisations()->removeElement($this);
        }

        if (
            $ownerAccount !== null &&
            !$ownerAccount->getOwnedOrganisations()->contains($this)
        ) {
            $ownerAccount->getOwnedOrganisations()->add($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProfileExperience>
     */
    public function getProfileExperiences(): Collection
    {
        return $this->profileExperiences;
    }
}
