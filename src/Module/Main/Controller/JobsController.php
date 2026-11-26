<?php

namespace App\Module\Main\Controller;

use App\Entity\Job\Job;
use App\Enum\JobEmploymentType;
use App\Enum\JobWorkMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/jobs', name: 'main.jobs.')]
final class JobsController extends AbstractController
{
    /** @var array<string, string> */
    private const EMPLOYMENT_TYPE_LABELS = [
        'employment' => 'Vast dienstverband',
        'internship' => 'Stage',
        'traineeship' => 'Traineeship',
        'freelance' => 'Freelance / zzp',
        'volunteer' => 'Vrijwilligerswerk',
        'other' => 'Overig',
    ];

    /** @var array<string, string> */
    private const WORK_MODE_LABELS = [
        'onsite' => 'Op locatie',
        'hybrid' => 'Hybride',
        'remote' => 'Thuiswerken / remote',
    ];

    #[Route('', name: 'home', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $filters = $this->readFilters($request->query->all());

        $qb = $em->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->andWhere('job.publishedAt IS NOT NULL')
            ->andWhere('(job.deadline IS NULL OR job.deadline >= :today)')
            ->setParameter('today', new \DateTimeImmutable('today'));

        if ($filters['search'] !== '') {
            $qb->leftJoin('job.organisation', 'organisation')
                ->andWhere(
                    '(LOWER(job.title) LIKE :search ESCAPE \'!\''
                    . ' OR LOWER(job.description) LIKE :search ESCAPE \'!\''
                    . ' OR LOWER(COALESCE(job.location, \'\')) LIKE :search ESCAPE \'!\''
                    . ' OR LOWER(COALESCE(organisation.name, \'\')) LIKE :search ESCAPE \'!\')'
                )
                ->setParameter('search', $this->likePattern($filters['search']));
        }

        if ($filters['employmentType'] !== '') {
            $qb->andWhere('job.employmentType = :employmentType')
                ->setParameter('employmentType', $filters['employmentType']);
        }

        if ($filters['workMode'] !== '') {
            $qb->andWhere('job.workMode = :workMode')
                ->setParameter('workMode', $filters['workMode']);
        }

        if ($filters['location'] !== '') {
            $qb->andWhere('LOWER(job.location) LIKE :location ESCAPE \'!\'')
                ->setParameter('location', $this->likePattern($filters['location']));
        }

        $jobs = $qb->orderBy('job.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('pages/main/jobs/index.html.twig', [
            'jobs' => $jobs,
            'filters' => $filters,
            'employmentTypeOptions' => self::options(JobEmploymentType::cases(), self::EMPLOYMENT_TYPE_LABELS),
            'workModeOptions' => self::options(JobWorkMode::cases(), self::WORK_MODE_LABELS),
            'employmentTypeLabels' => self::EMPLOYMENT_TYPE_LABELS,
            'workModeLabels' => self::WORK_MODE_LABELS,
        ]);
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(
        string $slug,
        EntityManagerInterface $em
    ): Response {
        $job = $em->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->andWhere('job.slug = :slug')
            ->andWhere('job.publishedAt IS NOT NULL')
            ->andWhere('(job.deadline IS NULL OR job.deadline >= :today)')
            ->setParameter('slug', $slug)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getOneOrNullResult();

        if ($job === null) {
            throw $this->createNotFoundException(
                'Vacature niet gevonden of niet meer beschikbaar.'
            );
        }

        return $this->render('pages/main/jobs/detail.html.twig', [
            'job' => $job,
        ]);
    }

    /**
     * Leest en valideert de filters uit de URL. Onbekende waarden worden
     * genegeerd zodat ze geen effect hebben op de query.
     *
     * @param array<string, mixed> $query
     *
     * @return array{search: string, employmentType: string, workMode: string, location: string, hasActiveFilters: bool}
     */
    private function readFilters(array $query): array
    {
        // 'q' is de standaard zoekparameter; 'search' wordt ook geaccepteerd
        // zodat de zoekfunctie en deze filters samenkunnen.
        $search = $this->stringValue($query, ['q', 'search'], 200);
        $location = $this->stringValue($query, ['location', 'locatie'], 255);

        $employmentTypeValue = $this->stringValue($query, ['employment_type', 'employmentType'], 40);
        $employmentType = JobEmploymentType::tryFrom($employmentTypeValue);

        $workModeValue = $this->stringValue($query, ['work_mode', 'workMode'], 30);
        $workMode = JobWorkMode::tryFrom($workModeValue);

        $filters = [
            'search' => $search,
            'employmentType' => $employmentType?->value ?? '',
            'workMode' => $workMode?->value ?? '',
            'location' => $location,
        ];

        return $filters + [
            'hasActiveFilters' => array_filter($filters, static fn (string $value): bool => $value !== '') !== [],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $names
     */
    private function stringValue(array $query, array $names, int $maxLength): string
    {
        foreach ($names as $name) {
            $value = $query[$name] ?? null;

            if (is_scalar($value)) {
                return mb_substr(trim((string) $value), 0, $maxLength);
            }
        }

        return '';
    }

    private function likePattern(string $value): string
    {
        return '%' . strtr(mb_strtolower($value), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }

    /**
     * @param list<JobEmploymentType|JobWorkMode> $cases
     * @param array<string, string> $labels
     *
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $cases, array $labels): array
    {
        return array_map(
            static fn (JobEmploymentType|JobWorkMode $case): array => [
                'value' => $case->value,
                'label' => $labels[$case->value] ?? ucfirst($case->value),
            ],
            $cases,
        );
    }
}
