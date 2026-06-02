<?php
namespace App\Module\Org\Service;

use App\Entity\Project\Project;
use Doctrine\Persistence\ManagerRegistry;

final class ProjectsViewService
{
	private ManagerRegistry $doctrine;

	public function __construct(ManagerRegistry $doctrine)
	{
		$this->doctrine = $doctrine;
	}

	/**
	 * Get projects with optional simple pagination.
	 *
	 * If $perPage is 0 the method returns all projects in `items` and a default pagination structure.
	 *
	 * @return array{ items: array<int, array{ id: string, title: ?string, summary: ?string, slug: ?string }>, pagination: array }
	 */
	public function getProjects(int $page = 1, int $perPage = 8): array
	{
		try {
			$repo = $this->doctrine->getRepository(Project::class);

			if ($perPage <= 0) {
				$projects = $repo->findBy([], ['createdAt' => 'DESC']);
				$items = [];
				foreach ($projects as $p) {
					if (!$p instanceof Project) {
						continue;
					}
					$items[] = [
						'id' => (string) $p->getId(),
						'title' => $p->getTitle(),
						'summary' => $p->getSummary(),
						'slug' => $p->getSlug(),
					];
				}

				return [
					'items' => $items,
					'pagination' => [
						'total' => count($items),
						'current_page' => 1,
						'per_page' => 0,
						'total_pages' => 1,
					],
				];
			}

			$page = max(1, $page);

			// total count
			$qbCount = $repo->createQueryBuilder('p')->select('COUNT(p.id)');
			$total = (int) $qbCount->getQuery()->getSingleScalarResult();

			$totalPages = (int) max(1, ceil($total / $perPage));
			if ($page > $totalPages) {
				$page = $totalPages;
			}

			$offset = ($page - 1) * $perPage;

			$qb = $repo->createQueryBuilder('p')
				->orderBy('p.createdAt', 'DESC')
				->setFirstResult($offset)
				->setMaxResults($perPage);

			$projects = $qb->getQuery()->getResult();

			$items = [];
			foreach ($projects as $p) {
				if (!$p instanceof Project) {
					continue;
				}

				$items[] = [
					'id' => (string) $p->getId(),
					'title' => $p->getTitle(),
					'summary' => $p->getSummary(),
					'slug' => $p->getSlug(),
				];
			}

			return [
				'items' => $items,
				'pagination' => [
					'total' => $total,
					'current_page' => $page,
					'per_page' => $perPage,
					'total_pages' => $totalPages,
				],
			];
		} catch (\Throwable $e) {
			// On any error, return an empty but valid pagination structure so Twig doesn't error out.
			return [
				'items' => [],
				'pagination' => [
					'total' => 0,
					'current_page' => 1,
					'per_page' => $perPage,
					'total_pages' => 1,
				],
			];
		}
	}
}

