<?php

namespace App\Entity\Project;

use App\Entity\Common\Status;
use App\Repository\Project\WorkPackageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WorkPackageRepository::class)]
#[ORM\Table(name: 'work_packages')]
#[ORM\UniqueConstraint(
    name: 'uniq_project_work_package_scope',
    columns: ['project_id', 'slug']
)]
#[ORM\HasLifecycleCallbacks]
class WorkPackage
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'workPackages')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'workPackages')]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $status = null;

    #[ORM\Column(name: 'slug', length: 255)]
    private ?string $slug = null;

    #[ORM\Column(name: 'due_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(name: 'title', length: 255)]
    private ?string $title = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    // Reverse FKs

    #[ORM\OneToMany(targetEntity: PackageTask::class, mappedBy: 'workPackage')]
    private Collection $workPackageTasks;


    // Functions

    public function __construct()
    {
        $this->id = new Ulid();
        $this->workPackageTasks = new ArrayCollection();
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

    public function clearDueDate(): void
    {
        $this->dueDate = null;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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
     * @return Collection<int, PackageTask>
     */
    public function getWorkPackageTasks(): Collection
    {
        return $this->workPackageTasks;
    }

    public function addWorkPackageTask(PackageTask $workPackageTask): static
    {
        if (!$this->workPackageTasks->contains($workPackageTask)) {
            $this->workPackageTasks->add($workPackageTask);
            $workPackageTask->setWorkPackage($this);
        }

        return $this;
    }

    public function removeWorkPackageTask(PackageTask $workPackageTask): static
    {
        if ($this->workPackageTasks->removeElement($workPackageTask)) {
            if ($workPackageTask->getWorkPackage() === $this) {
                $workPackageTask->setWorkPackage(null);
            }
        }

        return $this;
    }
}
