<?php

namespace App\Entity\Job;

use App\Entity\Org\Organisation;
use App\Enum\JobEmploymentType;
use App\Enum\JobWorkMode;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity]
#[ORM\Table(name: 'jobs')]
#[ORM\HasLifecycleCallbacks]
class Job
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    #[ORM\JoinColumn(name: 'organisation_id', nullable: false)]
    private ?Organisation $organisation = null;

    #[ORM\Column(name: 'title', length: 255)]
    private ?string $title = null;

    #[ORM\Column(name: 'slug', length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(name: 'description', type: 'text')]
    private ?string $description = null;

    #[ORM\Column(name: 'employment_type', length: 40, enumType: JobEmploymentType::class)]
    private ?JobEmploymentType $employmentType = null;

    #[ORM\Column(name: 'work_mode', length: 30, enumType: JobWorkMode::class)]
    private ?JobWorkMode $workMode = null;

    #[ORM\Column(name: 'application_url', type: 'text')]
    private ?string $applicationUrl = null;

    #[ORM\Column(name: 'location', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'salary_min', type: 'integer', nullable: true)]
    private ?int $salaryMin = null;

    #[ORM\Column(name: 'salary_max', type: 'integer', nullable: true)]
    private ?int $salaryMax = null;

    #[ORM\Column(name: 'salary_currency', length: 3, nullable: true)]
    private ?string $salaryCurrency = null;

    #[ORM\Column(name: 'hours_per_week_min', type: 'integer', nullable: true)]
    private ?int $hoursPerWeekMin = null;

    #[ORM\Column(name: 'hours_per_week_max', type: 'integer', nullable: true)]
    private ?int $hoursPerWeekMax = null;

    #[ORM\Column(name: 'requirements', type: 'text', nullable: true)]
    private ?string $requirements = null;

    #[ORM\Column(name: 'benefits', type: 'text', nullable: true)]
    private ?string $benefits = null;

    #[ORM\Column(name: 'deadline', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'source_url', type: 'text', nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(name: 'source_name', length: 120, nullable: true)]
    private ?string $sourceName = null;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    #[ORM\OneToMany(targetEntity: JobSkill::class, mappedBy: 'job')]
    private Collection $skills;

    #[ORM\OneToMany(targetEntity: \App\Entity\Account\SavedItem::class, mappedBy: 'job')]
    private Collection $savedByAccounts;

    public function __construct()
    {
        $this->id = new Ulid();

        $this->skills = new ArrayCollection();
        $this->savedByAccounts = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->ensureSlug();
        $this->lastModified = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->lastModified = new \DateTimeImmutable();
    }

    private function ensureSlug(): void
    {
        if ($this->slug !== null && $this->slug !== '') {
            return;
        }

        $this->generateSlug();
    }

    private function generateSlug(): void
    {
        $slugger = new AsciiSlugger();
        $base = strtolower((string) $slugger->slug($this->title ?? 'vacature'));
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'vacature';
        }

        $suffix = strtolower(substr((string) $this->id, -10));
        $maxBaseLength = 255 - strlen($suffix) - 1;

        $base = rtrim(substr($base, 0, $maxBaseLength), '-');

        $this->slug = $base . '-' . $suffix;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->id->getDateTime();
    }

    public function getLastModified(): \DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function getSavedByAccounts(): Collection
    {
        return $this->savedByAccounts;
    }

    public function getOrganisation(): ?Organisation
    {
        return $this->organisation;
    }

    public function setOrganisation(?Organisation $organisation): static
    {
        $this->organisation = $organisation;
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
        $slug = $slug !== null ? trim($slug) : null;

        if ($slug === null || $slug === '') {
            $this->generateSlug();
            return $this;
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException('De vacature-slug is ongeldig.');
        }

        if (strlen($slug) > 255) {
            throw new \InvalidArgumentException('De vacature-slug is te lang.');
        }

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

    public function getEmploymentType(): ?JobEmploymentType
    {
        return $this->employmentType;
    }

    public function setEmploymentType(?JobEmploymentType $employmentType): static
    {
        $this->employmentType = $employmentType;
        return $this;
    }

    public function getWorkMode(): ?JobWorkMode
    {
        return $this->workMode;
    }

    public function setWorkMode(?JobWorkMode $workMode): static
    {
        $this->workMode = $workMode;
        return $this;
    }

    public function getApplicationUrl(): ?string
    {
        return $this->applicationUrl;
    }

    public function setApplicationUrl(?string $applicationUrl): static
    {
        $this->applicationUrl = $applicationUrl;
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

    public function getSalaryMin(): ?int
    {
        return $this->salaryMin;
    }

    public function setSalaryMin(?int $salaryMin): static
    {
        $this->salaryMin = $salaryMin;
        return $this;
    }

    public function getSalaryMax(): ?int
    {
        return $this->salaryMax;
    }

    public function setSalaryMax(?int $salaryMax): static
    {
        $this->salaryMax = $salaryMax;
        return $this;
    }

    public function getSalaryCurrency(): ?string
    {
        return $this->salaryCurrency;
    }

    public function setSalaryCurrency(?string $salaryCurrency): static
    {
        $this->salaryCurrency = $salaryCurrency;
        return $this;
    }

    public function getHoursPerWeekMin(): ?int
    {
        return $this->hoursPerWeekMin;
    }

    public function setHoursPerWeekMin(?int $hoursPerWeekMin): static
    {
        $this->hoursPerWeekMin = $hoursPerWeekMin;
        return $this;
    }

    public function getHoursPerWeekMax(): ?int
    {
        return $this->hoursPerWeekMax;
    }

    public function setHoursPerWeekMax(?int $hoursPerWeekMax): static
    {
        $this->hoursPerWeekMax = $hoursPerWeekMax;
        return $this;
    }

    public function getRequirements(): ?string
    {
        return $this->requirements;
    }

    public function setRequirements(?string $requirements): static
    {
        $this->requirements = $requirements;
        return $this;
    }

    public function getBenefits(): ?string
    {
        return $this->benefits;
    }

    public function setBenefits(?string $benefits): static
    {
        $this->benefits = $benefits;
        return $this;
    }

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->deadline = $deadline;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;
        return $this;
    }

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function setSourceName(?string $sourceName): static
    {
        $this->sourceName = $sourceName;
        return $this;
    }

}
