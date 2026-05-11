<?php
namespace App\Entity\Project;

use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'project_participants', uniqueConstraints: [
    new ORM\UniqueConstraint(name: "uniq_project_participant_scope", columns: ["project_id", "profile_id"])
])]
#[ORM\HasLifecycleCallbacks]
class ProjectParticipant
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "participants")]
    #[ORM\JoinColumn(name: 'project_id', nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: "projectsParticipating")]
    #[ORM\JoinColumn(name: 'profile_id', nullable: false)]
    private ?Profile $profile = null;

    #[ORM\ManyToOne(inversedBy: "participants")]
    #[ORM\JoinColumn(name: 'role_id', nullable: true)]
    private ?ProjectRole $role = null;

    #[ORM\ManyToOne(inversedBy: "projectParticipants")]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $status = null;

    #[ORM\OneToOne(inversedBy: "participant")]
    #[ORM\JoinColumn(name: 'application_id', nullable: true)]
    private ?ProjectApplication $application = null;

    #[ORM\Column(name: 'joined_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $joinedAt = null;

    #[ORM\Column(name: 'left_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $leftAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_modified', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastModified;


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
}
