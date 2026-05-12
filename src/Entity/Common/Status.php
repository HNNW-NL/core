<?php
namespace App\Entity\Common;

use App\Entity\Account\Account;
use App\Entity\Account\Review;
use App\Entity\Admin\Admin;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\WorkPackage;
Use App\Entity\Project\PackageTask;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'statuses', uniqueConstraints: [
    new ORM\UniqueConstraint(name: "uniq_status_name_scope", columns: ["name", "scope"])
])]
class Status
{
    // Columns

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'name', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'colour_hex', length: 9)]
    private ?string $colourHex = null;

    #[ORM\Column(name: 'scope', length: 255)]
    private ?string $scope = null;


    // Reverse FKs

    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'status')]
    private Collection $accounts;

    #[ORM\OneToMany(targetEntity: Organisation::class, mappedBy: 'status')]
    private Collection $organisations;

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'status')]
    private Collection $projects;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'status')]
    private Collection $reviews;

    #[ORM\OneToMany(targetEntity: ProjectApplication::class, mappedBy: 'status')]
    private Collection $projectApplications;

    #[ORM\OneToMany(targetEntity: ProjectParticipant::class, mappedBy: 'status')]
    private Collection $projectParticipants;

    #[ORM\OneToMany(targetEntity: WorkPackage::class, mappedBy: 'status')]
    private Collection $workPackages;

    #[ORM\OneToMany(targetEntity: PackageTask::class, mappedBy: 'status')]
    private Collection $workPackageTasks;

    #[ORM\OneToMany(targetEntity: Admin::class, mappedBy: 'status')]
    private Collection $admins;


    // Functions

    public function __construct()
    {
        $this->id = Uuid::v7();

        $this->accounts = new ArrayCollection();
        $this->organisations = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->projectApplications = new ArrayCollection();
        $this->projectParticipants = new ArrayCollection();
        $this->workPackages = new ArrayCollection();
        $this->workPackageTasks = new ArrayCollection();
        $this->admins = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}
