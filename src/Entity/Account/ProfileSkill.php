<?php
namespace App\Entity\Account;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'profile_skills', uniqueConstraints: [
    new ORM\UniqueConstraint(name: "uniq_profile_skills", columns: ["profile_id", "skill_id"])
])]
#[ORM\HasLifecycleCallbacks]
class ProfileSkill
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: "profileSkills")]
    #[ORM\JoinColumn(name: 'profile_id', nullable: false)]
    private ?Profile $profile = null;

    #[ORM\ManyToOne(inversedBy: "skillRecords")]
    #[ORM\JoinColumn(name: 'skill_id', nullable: false)]
    private ?Skill $skill = null;

    #[ORM\Column(name: 'proficiency_level', length: 50)]
    private ?string $proficiencyLevel = null;

    #[ORM\Column(name: 'is_featured', type: "boolean", nullable: false)]
    private bool $featured = false;

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
