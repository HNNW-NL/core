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
	 * Return simple project data for listing.
	 *
	 * @return array<int, array{ id: string, title: ?string, summary: ?string, slug: ?string }>
	 */
	public function getProjects(int $limit = 0): array
	{
		try {
			$repo = $this->doctrine->getRepository(Project::class);
			$orderBy = ['createdAt' => 'DESC'];
			$projects = $limit > 0 ? $repo->findBy([], $orderBy, $limit) : $repo->findBy([], $orderBy);

			$result = [];
			foreach ($projects as $p) {
				if (!$p instanceof Project) {
					continue;
				}

				$result[] = [
					'id' => (string) $p->getId(),
					'title' => $p->getTitle(),
					'summary' => $p->getSummary(),
					'slug' => $p->getSlug(),
				];
			}

			return $result;
		} catch (\Throwable $e) {
			return [];
		}
	}
}

