<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Security\ProjectParticipation;
use App\Workflow\ProjectApplicationWorkflow;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectApplicationReviewService
{
    private const APPLICATION_SCOPE = ProjectApplicationWorkflow::APPLICATION_SCOPE;
    private const ACCEPTED_NAMES = ProjectApplicationWorkflow::ACCEPTED_NAMES;
    private const REJECTED_NAMES = ProjectApplicationWorkflow::REJECTED_NAMES;
    private const PARTICIPANT_SCOPE = 'ProjectParticipant';
    private const PARTICIPANT_ACTIVE_NAMES = ProjectParticipation::ACTIVE_NAMES;
    private const REJECT_REASON_MAX_LENGTH = ProjectApplicationWorkflow::REJECT_REASON_MAX_LENGTH;

    public function __construct(
        private ProjectParticipantRepository $participantRepo,
        private EntityManagerInterface $em,
        private StatusRepository $statusRepo,
    ) {}

    /**
     * OR06-B: accepteert een aanvraag als één atomaire overgang.
     *
     * Alles gebeurt in één databasetransactie waarin de aanvraag eerst wordt
     * vergrendeld (SELECT ... FOR UPDATE). Een tweede, gelijktijdige klik wacht
     * dan tot de eerste klaar is, ziet de nieuwe status en maakt dus geen
     * tweede participant. Faalt er iets, dan wordt alles teruggedraaid.
     *
     * @return array{success: bool, message: string}
     */
    public function acceptApplication(Project $project, string $applicationId, Profile $reviewer): array
    {
        try {
            return $this->em->wrapInTransaction(
                fn (): array => $this->doAcceptApplication($project, $applicationId, $reviewer)
            );
        } catch (UniqueConstraintViolationException) {
            // Vangnet: de database weigert een tweede participant voor dezelfde aanvraag (unieke application_id).
            return $this->failure('Deze aanvraag is al behandeld of de deelnemer bestaat al.');
        }
    }

    /**
     * Wijst een aanvraag af (ook binnen een vergrendelde transactie); een korte reden is verplicht.
     * Afwijzen maakt nooit een participant aan.
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

        return $this->em->wrapInTransaction(
            fn (): array => $this->doRejectApplication($project, $applicationId, $reason, $reviewer)
        );
    }

    /** @return array{success: bool, message: string} */
    private function doAcceptApplication(Project $project, string $applicationId, Profile $reviewer): array
    {
        // Eerst het project vergrendelen: zo kunnen er per project nooit twee accepties tegelijk lopen. Dat
        // garandeert één participant per profiel/project, ook zonder extra unieke index in de database.
        $this->em->lock($project, LockMode::PESSIMISTIC_WRITE);
        $this->em->refresh($project);

        // Vergrendelen en daarna pas de status controleren: zo ziet een wachtende tweede klik de nieuwe status.
        $application = $this->participantRepo->findApplicationForProject($applicationId, $project, true);

        if ($application === null) {
            // Ook een ingetrokken (soft-deleted) aanvraag komt hier uit: intrekken maakt dus nooit een participant.
            return $this->failure('Aanvraag niet gevonden.');
        }

        if (!ProjectApplicationWorkflow::matches($application->getStatus(), ProjectApplicationWorkflow::PENDING_NAMES)) {
            return $this->failure('Deze aanvraag is al behandeld.');
        }

        if (!$application->getStatus()->allowsApplicationAcceptance()) {
            return $this->failure('Accepteren is uitgeschakeld voor deze status.');
        }
        $acceptedStatus = $this->statusRepo->findFirstByScopeAndNames(self::APPLICATION_SCOPE, self::ACCEPTED_NAMES);
        $activeStatus = $this->statusRepo->findFirstByScopeAndNames(self::PARTICIPANT_SCOPE, self::PARTICIPANT_ACTIVE_NAMES);

        if ($acceptedStatus === null || $activeStatus === null) {
            return $this->failure('De benodigde statussen (accepted / active) ontbreken in de database. Laad de status-fixtures opnieuw.');
        }

        $applicant = $application->getProfile();
        if (!$this->participantRepo->isActiveParticipant($project, $applicant)
            && !ProjectLifecycle::hasAvailableCapacity($project, $this->participantRepo->countActiveProfiles($project))) {
            return $this->failure('De maximale deelnemerscapaciteit is bereikt.');
        }

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

        // De flush gebeurt door wrapInTransaction: aanvraag en participant worden samen vastgelegd.

        return [
            'success' => true,
            'message' => sprintf('Aanvraag van %s is geaccepteerd. De deelnemer is toegevoegd aan het project.', $this->applicantName($application)),
        ];
    }

    /** @return array{success: bool, message: string} */
    private function doRejectApplication(Project $project, string $applicationId, string $reason, Profile $reviewer): array
    {
        // Gebruik dezelfde lockvolgorde als bij accepteren: project, dan aanvraag.
        $this->em->lock($project, LockMode::PESSIMISTIC_WRITE);
        $this->em->refresh($project);
        $application = $this->participantRepo->findApplicationForProject($applicationId, $project, true);

        if ($application === null) {
            return $this->failure('Aanvraag niet gevonden.');
        }

        if (!ProjectApplicationWorkflow::matches($application->getStatus(), ProjectApplicationWorkflow::PENDING_NAMES)) {
            return $this->failure('Deze aanvraag is al behandeld.');
        }

        if (!$application->getStatus()->allowsApplicationRejection()) {
            return $this->failure('Afwijzen is uitgeschakeld voor deze status.');
        }
        $rejectedStatus = $this->statusRepo->findFirstByScopeAndNames(self::APPLICATION_SCOPE, self::REJECTED_NAMES);

        if ($rejectedStatus === null) {
            return $this->failure('De benodigde status (rejected) ontbreekt in de database. Laad de status-fixtures opnieuw.');
        }

        $application->setStatus($rejectedStatus);
        $application->setReview($reason);
        $application->setReviewerProfile($reviewer);
        $application->markAsReviewed();

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
