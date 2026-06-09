<?php
namespace App\Module\Org\Service;

use App\Entity\Project\Project;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

final class ProjectsViewService
{
	private ManagerRegistry $doctrine;
	private LoggerInterface $logger;

	public function __construct(ManagerRegistry $doctrine, LoggerInterface $logger)
	{
		$this->doctrine = $doctrine;
		$this->logger = $logger;
	}

	/**
	 * Get all projects matching search query.
	 *
	 * @return array{ items: array<int, array{ id: string, title: ?string, summary: ?string, slug: ?string }> }
	 */
	public function getProjects(string $q = ''): array
	{
		try {
			$conn = $this->doctrine->getConnection();

			if ($q !== '') {
				$sql = 'SELECT id, title, summary, slug FROM projects WHERE title ILIKE :q OR summary ILIKE :q ORDER BY id DESC';
				$result = $conn->executeQuery($sql, ['q' => '%' . $q . '%']);
			} else {
				$sql = 'SELECT id, title, summary, slug FROM projects ORDER BY id DESC';
				$result = $conn->executeQuery($sql);
			}
			$rows = $result->fetchAllAssociative();

			$items = array_map(function (array $r) {
				return [
					'id' => isset($r['id']) ? (string) $r['id'] : '',
					'title' => $r['title'] ?? null,
					'summary' => $r['summary'] ?? null,
					'slug' => $r['slug'] ?? null,
				];
			}, $rows);

			return [
				'items' => $items,
			];
		} catch (\Throwable $e) {
			$this->logger->error('ProjectsViewService::getProjects failed', [
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
			]);
			return [
				'items' => [],
			];
		}
	}
}

