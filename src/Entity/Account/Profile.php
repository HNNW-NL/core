<?php

namespace App\Entity\Account;

use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectUpdate;
use App\Entity\Project\PackageTask;
use App\Repository\Account\ProfileRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
#[ORM\Table(name: 'profiles')]
#[ORM\HasLifecycleCallbacks]
class Profile
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(inversedBy: "profile")]
    #[ORM\JoinColumn(name: 'account_id', unique: true, nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(name: 'first_name', length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'display_name', length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(name: 'avatar_url', type: "text")]
    private ?string $avatarUrl = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'headline', length: 180, nullable: true)]
    private ?string $headline = null;

    #[ORM\ManyToOne(
        targetEntity: ProfileExperience::class,
        inversedBy: 'profilesUsingAsCurrentExperience'
    )]
    #[ORM\JoinColumn(
        name: 'current_experience_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private ?ProfileExperience $currentExperience = null;

    #[ORM\Column(name: 'preferred_work_mode', length: 24, nullable: true)]
    private ?string $preferredWorkMode = null;

    #[ORM\Column(name: 'open_to_opportunities', type: 'boolean', options: ['default' => false])]
    private bool $openToOpportunities = false;

    #[ORM\Column(name: 'location', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'is_online', type: "boolean", nullable: false)]
    private bool $online = false;

    #[ORM\Column(name: 'points', type: "integer")]
    private ?int $points = 0;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;


    // Reverse FKs

    #[ORM\OneToMany(targetEntity: ProfileSocialLink::class, mappedBy: "profile")]
    private Collection $profileSocialLinks;

    #[ORM\OneToMany(targetEntity: ProfileSkill::class, mappedBy: "profile")]
    private Collection $profileSkills;

    #[ORM\OneToMany(targetEntity: ProfileSkillsInterest::class, mappedBy: "profile")]
    private Collection $profileInterestSkills;

    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: "profile")]
    private Collection $availabilities;

    #[ORM\OneToMany(targetEntity: ProfileExperience::class, mappedBy: "profile")]
    private Collection $experiences;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: "profile")]
    private Collection $reviews;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: "reviewerProfile")]
    private Collection $sentReviews;

    #[ORM\OneToMany(targetEntity: ProjectApplication::class, mappedBy: "profile")]
    private Collection $projectApplications;

    #[ORM\OneToMany(targetEntity: ProjectApplication::class, mappedBy: "reviewerProfile")]
    private Collection $reviewedProjectApplications;

    #[ORM\OneToMany(targetEntity: ProjectParticipant::class, mappedBy: "profile")]
    private Collection $projectsParticipating;

    #[ORM\OneToMany(targetEntity: ProjectUpdate::class, mappedBy: 'author')]
    private Collection $authoredProjectUpdates;

    #[ORM\OneToMany(targetEntity: PackageTask::class, mappedBy: 'assignedProfile')]
    private Collection $assignedWorkPackageTasks;


    public function __construct()
    {
        $this->id = new Ulid();

        $this->profileSocialLinks = new ArrayCollection();
        $this->profileSkills = new ArrayCollection();
        $this->profileInterestSkills = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->experiences = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->sentReviews = new ArrayCollection();
        $this->projectApplications = new ArrayCollection();
        $this->reviewedProjectApplications = new ArrayCollection();
        $this->projectsParticipating = new ArrayCollection();
        $this->authoredProjectUpdates = new ArrayCollection();
        $this->assignedWorkPackageTasks = new ArrayCollection();
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

    public function goOnline(): void
    {
        $this->online = true;
    }

    public function goOffline(): void
    {
        $this->online = false;
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

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;
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

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): static
    {
        $this->headline = $headline;
        return $this;
    }

    public function getCurrentExperience(): ?ProfileExperience
    {
        return $this->currentExperience;
    }

    public function setCurrentExperience(?ProfileExperience $experience): static
    {
        if ($experience !== null && $experience->getProfile() !== $this) {
            throw new \InvalidArgumentException(
                'The selected experience does not belong to this profile.'
            );
        }

        if ($this->currentExperience === $experience) {
            return $this;
        }

        $previousExperience = $this->currentExperience;

        $this->currentExperience = $experience;

        if ($previousExperience !== null) {
            $previousExperience
                ->getProfilesUsingAsCurrentExperience()
                ->removeElement($this);
        }

        if ($experience !== null &&
            !$experience->getProfilesUsingAsCurrentExperience()->contains($this)) {
            $experience->getProfilesUsingAsCurrentExperience()->add($this);
        }

        return $this;
    }

    public function getPreferredWorkMode(): ?string
    {
        return $this->preferredWorkMode;
    }

    public function setPreferredWorkMode(?string $preferredWorkMode): static
    {
        $this->preferredWorkMode = $preferredWorkMode;
        return $this;
    }

    public function isOpenToOpportunities(): bool
    {
        return $this->openToOpportunities;
    }

    public function setOpenToOpportunities(bool $openToOpportunities): static
    {
        $this->openToOpportunities = $openToOpportunities;
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

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(?int $points): static
    {
        $this->points = $points;
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

    /**
     * @return Collection<int, ProjectApplication>
     */
    public function getProjectApplications(): Collection
    {
        return $this->projectApplications;
    }
}
