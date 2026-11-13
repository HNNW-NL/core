<?php

namespace App\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Job\Job;
use App\Entity\Org\Organisation;
use App\Enum\JobEmploymentType;
use App\Enum\JobWorkMode;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/org/jobs', name: 'org.jobs.')]
final class OrganisationJobsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $organisations = $this->accessibleOrganisations($em);
        $organisation = $this->selectedOrganisation($request, $em, $organisations);
        $jobs = $organisation === null ? [] : $em->getRepository(Job::class)->findBy(
            ['organisation' => $organisation],
            ['lastModified' => 'DESC']
        );

        return $this->render('pages/org/jobs/index.html.twig', compact('organisations', 'organisation', 'jobs'));
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $organisations = $this->accessibleOrganisations($em);
        $organisation = $this->selectedOrganisation($request, $em, $organisations);
        if ($organisation === null) {
            throw $this->createAccessDeniedException('Geen toegankelijke organisatie geselecteerd.');
        }
        $this->denyAccessUnlessGranted(Permission::ORG_CREATE_JOBS->name, $organisation);
        $job = new Job();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('org_job_create', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
            }
            $error = $this->fillJob($job, $request);
            if ($error === null) {
                $job->setOrganisation($organisation);
                $em->persist($job);
                $em->flush();
                $this->addFlash('success', 'Vacature aangemaakt.');
                return $this->redirectToRoute('org.jobs.home', ['organisation' => (string) $organisation->getId()]);
            }
            $this->addFlash('error', $error);
        }
        return $this->render('pages/org/jobs/form.html.twig', [
            'job' => $job, 'organisation' => $organisation, 'organisations' => $organisations,
            'mode' => 'create', 'employmentTypes' => JobEmploymentType::cases(),
            'workModes' => JobWorkMode::cases(),
        ]);
    }

    #[Route('/{id}', name: 'detail', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
    public function detail(string $id, EntityManagerInterface $em): Response
    {
        $job = $this->findJob($id, $em);
        $this->denyAccessUnlessGranted(Permission::ORG_VIEW_JOBS->name, $job->getOrganisation());
        return $this->render('pages/org/jobs/detail.html.twig', ['job' => $job]);
    }

    #[Route('/{id}/modify', name: 'modify', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET', 'POST'])]
    public function modify(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $job = $this->findJob($id, $em);
        $this->denyAccessUnlessGranted(Permission::ORG_EDIT_JOBS->name, $job->getOrganisation());
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('org_job_edit_' . $id, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
            }
            $error = $this->fillJob($job, $request);
            if ($error === null) {
                $em->flush();
                $this->addFlash('success', 'Vacature bijgewerkt.');
                return $this->redirectToRoute('org.jobs.detail', ['id' => $id]);
            }
            $this->addFlash('error', $error);
        }
        return $this->render('pages/org/jobs/form.html.twig', [
            'job' => $job, 'organisation' => $job->getOrganisation(), 'mode' => 'edit',
            'employmentTypes' => JobEmploymentType::cases(), 'workModes' => JobWorkMode::cases(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function delete(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $job = $this->findJob($id, $em);
        $this->denyAccessUnlessGranted(Permission::ORG_DELETE_JOBS->name, $job->getOrganisation());
        if (!$this->isCsrfTokenValid('org_job_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
        }
        $organisationId = (string) $job->getOrganisation()->getId();
        // Unpublish rather than remove: related saved items and skills may reference this job.
        $job->setPublishedAt(null);
        $em->flush();
        $this->addFlash('success', 'Vacature ingetrokken.');
        return $this->redirectToRoute('org.jobs.home', ['organisation' => $organisationId]);
    }

    #[Route('/{id}/publish', name: 'publish', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['POST'])]
    public function publish(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $job = $this->findJob($id, $em);
        $this->denyAccessUnlessGranted(Permission::ORG_EDIT_JOBS->name, $job->getOrganisation());
        if (!$this->isCsrfTokenValid('org_job_publish_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ongeldige CSRF-token.');
        }
        $job->setPublishedAt(new \DateTimeImmutable());
        $em->flush();
        return $this->redirectToRoute('org.jobs.detail', ['id' => $id]);
    }

    private function accessibleOrganisations(EntityManagerInterface $em): array
    {
        $account = $this->getUser();
        if (!$account instanceof Account) {
            throw $this->createAccessDeniedException('Log eerst in.');
        }
        $result = [];
        foreach ($account->getOrgMemberships() as $membership) {
            $organisation = $membership->getOrganisation();
            if ($organisation !== null && $organisation->getDeletedAt() === null
                && $this->isGranted(Permission::ORG_VIEW_JOBS->name, $organisation)) {
                $result[(string) $organisation->getId()] = $organisation;
            }
        }
        foreach ($account->getOwnedOrganisations() as $organisation) {
            if ($organisation->getDeletedAt() === null
                && $this->isGranted(Permission::ORG_VIEW_JOBS->name, $organisation)) {
                $result[(string) $organisation->getId()] = $organisation;
            }
        }
        return array_values($result);
    }

    private function selectedOrganisation(Request $request, EntityManagerInterface $em, array $organisations): ?Organisation
    {
        $id = (string) ($request->isMethod('POST')
            ? $request->request->get('organisation', '')
            : $request->query->get('organisation', ''));
        if ($id === '' && count($organisations) === 1) {
            return $organisations[0];
        }
        foreach ($organisations as $organisation) {
            if ((string) $organisation->getId() === $id) {
                return $organisation;
            }
        }
        if ($id !== '') {
            throw $this->createAccessDeniedException('Deze organisatie is niet toegankelijk.');
        }
        return null;
    }

    private function findJob(string $id, EntityManagerInterface $em): Job
    {
        if (!Ulid::isValid($id)) {
            throw $this->createNotFoundException('Vacature niet gevonden.');
        }
        $job = $em->getRepository(Job::class)->find(Ulid::fromString($id));
        if (!$job instanceof Job || $job->getOrganisation() === null) {
            throw $this->createNotFoundException('Vacature niet gevonden.');
        }
        return $job;
    }

    private function fillJob(Job $job, Request $request): ?string
    {
        $title = trim((string) $request->request->get('title', ''));
        $description = trim((string) $request->request->get('description', ''));
        $url = trim((string) $request->request->get('application_url', ''));
        $employment = JobEmploymentType::tryFrom((string) $request->request->get('employment_type', ''));
        $mode = JobWorkMode::tryFrom((string) $request->request->get('work_mode', ''));
        if ($title === '' || mb_strlen($title) > 255 || $description === '' || $employment === null || $mode === null) {
            return 'Vul een geldige titel, beschrijving, dienstverband en werkwijze in.';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return 'Gebruik een geldige HTTP(S)-sollicitatielink.';
        }
        $job->setTitle($title)
            ->setDescription($description)
            ->setApplicationUrl($url)
            ->setEmploymentType($employment)
            ->setWorkMode($mode)
            ->setLocation(mb_substr(trim((string) $request->request->get('location', '')), 0, 255) ?: null);
        return null;
    }
}
