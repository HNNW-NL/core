<?php


namespace App\Module\Org\Handler;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectParticipantUpdateRoleHandler
{
    // OR06-A: aanvragen beoordelen
    public const REJECT_REASON_MAX_LENGTH = 500;

    private const APPLICATION_SCOPE = 'ProjectApplication';
    private const PARTICIPANT_SCOPE = 'ProjectParticipant';

    /** 'send' komt uit de bestaande fixtures; de rest zijn toegestane synoniemen. */
    private const PENDING_NAMES = ['send', 'sent', 'pending', 'submitted', 'ingediend'];
    private const ACCEPTED_NAMES = ['accepted', 'geaccepteerd'];
    private const REJECTED_NAMES = ['rejected', 'afgewezen'];
    private const PARTICIPANT_ACTIVE_NAMES = ['active', 'actief'];

    public function __construct(
        private ProjectParticipantRepository $participantRepo,
        private ProjectRoleRepository $roleRepo,
        private EntityManagerInterface $em,
        private StatusRepository $statusRepo,
    ) {}

    /**
     * @param array<int, int> $roleMap [participantId => roleId]
     */
    public function handle(array $roleMap): void
    {
        foreach ($roleMap as $participantId => $roleId) {

            $participant = $this->participantRepo->find($participantId);
            $role = $this->roleRepo->find($roleId);

            if (!$participant) {
                continue;
            }

            $participant->setRole($role);
        }

        $this->em->flush();
    }

    // ------------------------------------------------------------------
    // OR06-A: ingediende aanvragen accepteren / afwijzen
    // ------------------------------------------------------------------

    public function isPending(ProjectApplication $application): bool
    {
        $name = mb_strtolower((string) $application->getStatus()?->getName());

        return in_array($name, self::PENDING_NAMES, true);
    }

    /**
     * Accepteert een aanvraag en maakt precies één participant aan (of
     * heractiveert een bestaande voor dezelfde profiel/project-combinatie).
     *
     * @return array{success: bool, message: string}
     */
    public function acceptApplication(Project $project, string $applicationId, Profile $reviewer): array
    {
        $application = $this->participantRepo->findApplicationForProject($applicationId, $project);

        if ($application === null) {
            return $this->failure('Aanvraag niet gevonden.');
        }

        if (!$this->isPending($application)) {
            return $this->failure('Deze aanvraag is al behandeld.');
        }

        $acceptedStatus = $this->statusRepo->findFirstByScopeAndNames(self::APPLICATION_SCOPE, self::ACCEPTED_NAMES);
        $activeStatus = $this->statusRepo->findFirstByScopeAndNames(self::PARTICIPANT_SCOPE, self::PARTICIPANT_ACTIVE_NAMES);

        if ($acceptedStatus === null || $activeStatus === null) {
            return $this->failure('De benodigde statussen (accepted / active) ontbreken in de database. Laad de status-fixtures opnieuw.');
        }

        $applicant = $application->getProfile();

        // Eén participant per profiel/project: bestaat die al (bijv. na eerder vertrek), dan heractiveren we hem.
        $participant = $this->participantRepo->findOneBy([
            'project' => $project,
            'profile' => $applicant,
        ]);

        if ($participant === null) {
            $participant = new ProjectParticipant();
            $participant->setProject($project);
            $participant->setProfile($applicant);
            $this->em->persist($participant);
        }

        $participant->setApplication($application);
        $participant->setStatus($activeStatus);
        $participant->join();

        $application->setStatus($acceptedStatus);
        $application->setReview(null);
        $application->setReviewerProfile($reviewer);
        $application->markAsReviewed();

        // Eén flush = één Doctrine-transactie: aanvraag en participant worden samen opgeslagen.
        $this->em->flush();

        return [
            'success' => true,
            'message' => sprintf('Aanvraag van %s is geaccepteerd. De deelnemer is toegevoegd aan het project.', $this->applicantName($application)),
        ];
    }

    /**
     * Wijst een aanvraag af; een korte reden is verplicht.
     *
     * @return array{success: bool, message: string}
     */
    public function rejectApplication(Project $project, string $applicationId, ?string $reason, Profile $reviewer): array
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            return $this->failure('Geef een korte reden op voor de afwijzing.');
        }

        if (mb_strlen($reason) > self::REJECT_REASON_MAX_LENGTH) {
            return $this->failure(sprintf('De reden mag maximaal %d tekens bevatten.', self::REJECT_REASON_MAX_LENGTH));
        }

        $application = $this->participantRepo->findApplicationForProject($applicationId, $project);

        if ($application === null) {
            return $this->failure('Aanvraag niet gevonden.');
        }

        if (!$this->isPending($application)) {
            return $this->failure('Deze aanvraag is al behandeld.');
        }

        $rejectedStatus = $this->statusRepo->findFirstByScopeAndNames(self::APPLICATION_SCOPE, self::REJECTED_NAMES);

        if ($rejectedStatus === null) {
            return $this->failure('De benodigde status (rejected) ontbreekt in de database. Laad de status-fixtures opnieuw.');
        }

        $application->setStatus($rejectedStatus);
        $application->setReview($reason);
        $application->setReviewerProfile($reviewer);
        $application->markAsReviewed();

        $this->em->flush();

        return [
            'success' => true,
            'message' => sprintf('Aanvraag van %s is afgewezen.', $this->applicantName($application)),
        ];
    }

    /** @return array{success: bool, message: string} */
    private function failure(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    private function applicantName(ProjectApplication $application): string
    {
        $profile = $application->getProfile();

        if ($profile === null) {
            return 'onbekend';
        }

        $displayName = trim((string) $profile->getDisplayName());

        if ($displayName !== '') {
            return $displayName;
        }

        return trim($profile->getFirstName() . ' ' . $profile->getLastName());
    }
}