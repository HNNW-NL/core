<?php
namespace App\Entity\Account;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'profile_experiences')]
#[ORM\HasLifecycleCallbacks]
class ProfileExperience
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "experiences")]
    #[ORM\JoinColumn(name: 'profile_id', nullable: false)]
    private ?Profile $profile = null;

    #[ORM\Column(name: 'organisation_name', length: 255)]
    private ?string $organisationName = null;

    #[ORM\Column(name: 'job_title', length: 255)]
    private ?string $jobTitle = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'start_date', type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'end_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(name: 'is_current', type: "boolean", nullable: false)]
    private bool $isCurrent = false;

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
