<?php

namespace App\Module\Main\Controller;

use App\Entity\Job\Job;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/jobs', name: 'main.jobs.')]
final class JobsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $jobs = $em->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->andWhere('job.publishedAt IS NOT NULL')
            ->andWhere('(job.deadline IS NULL OR job.deadline >= :today)')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('job.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('pages/main/jobs/index.html.twig', [
            'jobs' => $jobs,
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
}
