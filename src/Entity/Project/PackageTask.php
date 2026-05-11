<?php
namespace App\Entity\Project;

use App\Entity\Common\Status;
use App\Entity\Account\Profile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'package_tasks', uniqueConstraints: [
    new ORM\UniqueConstraint(name: "uniq_work_package_task_scope", columns: ["work_package_id", "slug"])
])]
#[ORM\HasLifecycleCallbacks]
class PackageTask
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "workPackageTasks")]
    #[ORM\JoinColumn(name: 'work_package_id', nullable: false)]
    private ?WorkPackage $workPackage = null;

    #[ORM\ManyToOne(inversedBy: "workPackageTasks")]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $status = null;

    #[ORM\Column(name: 'slug', length: 255)]
    private ?string $slug = null;

    #[ORM\Column(name: 'due_date', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(name: 'priority', length: 25)]
    private string $priority = "low";

    #[ORM\Column(name: 'title', length: 255)]
    private ?string $title = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: "assignedWorkPackageTasks")]
    #[ORM\JoinColumn(name: 'assigned_profile_id', nullable: true)]
    private ?Profile $assignedProfile = null;

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
