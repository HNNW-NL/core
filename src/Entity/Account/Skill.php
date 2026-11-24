<?php

namespace App\Entity\Account;

use App\Entity\Job\JobSkill;
use App\Entity\Project\ProjectSkill;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'skills')]
class Skill
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(name: 'name', length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'slug', length: 255, unique: true, nullable: false)]
    private ?string $slug = null;

    #[ORM\Column(name: 'category', length: 255)]
    private ?string $category = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $active = true;


    // Reverse FKs

    #[ORM\OneToMany(mappedBy: "skill", targetEntity: ProfileSkill::class)]
    private Collection $skillRecords;

    #[ORM\OneToMany(mappedBy: "skill", targetEntity: ProfileSkillsInterest::class)]
    private Collection $skillInterestsRecords;


    // Functions

    /** @var Collection<int, JobSkill> */
    #[ORM\OneToMany(
        targetEntity: JobSkill::class,
        mappedBy: 'skill'
    )]
    private Collection $jobSkills;

    /** @var Collection<int, ProjectSkill> */
    #[ORM\OneToMany(
        targetEntity: ProjectSkill::class,
        mappedBy: 'skill'
    )]
    private Collection $projectSkills;


    public function __construct()
    {
        $this->id = new Ulid();
        $this->projectSkills = new ArrayCollection();
        $this->jobSkills = new ArrayCollection();

        $this->skillRecords = new ArrayCollection();
        $this->skillInterestsRecords = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }


    /// Getters & Setters Functions

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /** @return Collection<int, ProfileSkill> */
    public function getSkillRecords(): Collection
    {
        return $this->skillRecords;
    }

    /** @return Collection<int, ProfileSkillsInterest> */
    public function getSkillInterestsRecords(): Collection
    {
        return $this->skillInterestsRecords;
    }

    /** @return Collection<int, JobSkill> */
    public function getJobSkills(): Collection
    {
        return $this->jobSkills;
    }

    /** @return Collection<int, ProjectSkill> */
    public function getProjectSkills(): Collection
    {
        return $this->projectSkills;
    }

}
