<?php
namespace App\Entity\Account;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'skills')]
class Skill
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'name', length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'slug', length: 255, unique: true, nullable: false)]
    private ?string $slug = null;

    #[ORM\Column(name: 'category', length: 255)]
    private ?string $category = null;


    // Reverse FKs

    #[ORM\OneToMany(mappedBy: "skill", targetEntity: ProfileSkill::class)]
    private Collection $skillRecords;

    #[ORM\OneToMany(mappedBy: "skill", targetEntity: ProfileSkillsInterest::class)]
    private Collection $skillInterestsRecords;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->skillRecords = new ArrayCollection();
        $this->skillInterestsRecords = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}
