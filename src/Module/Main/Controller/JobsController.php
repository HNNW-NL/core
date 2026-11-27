<?php

namespace App\Module\Main\Controller;

use App\Entity\Job\Job;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/jobs', name: 'main.jobs.')]
final class JobsController extends AbstractController
{
    private const MAX_SEARCH_LENGTH = 100;
    private const MAX_TERMS = 5;

    #[Route('', name: 'home', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $q = $request->query->get('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, self::MAX_SEARCH_LENGTH) : '';

        $qb = $em->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->andWhere('job.publishedAt IS NOT NULL')
            ->andWhere('(job.deadline IS NULL OR job.deadline >= :today)')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('job.publishedAt', 'DESC');

        if ($q !== '') {
            $terms = preg_split('/\s+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $terms = array_slice(array_unique($terms), 0, self::MAX_TERMS);


            foreach ($terms as $i => $term) {
                $param = 'term' . $i;

                $qb->andWhere(
                    "(LOWER(job.title) LIKE :$param ESCAPE '!'
                      OR LOWER(job.description) LIKE :$param ESCAPE '!'
                      OR LOWER(job.location) LIKE :$param ESCAPE '!')"
                )->setParameter(
                    $param,
                    '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%'
                );
            }
        }

        return $this->render('pages/main/jobs/index.html.twig', [
            'jobs' => $qb->getQuery()->getResult(),
            'q' => $q,
        ]);
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(string $slug, EntityManagerInterface $em): Response
    {
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
}
