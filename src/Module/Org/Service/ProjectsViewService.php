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
	 * Get projects with optional simple pagination.
	 *
	 * If $perPage is 0 the method returns all projects in `items` and a default pagination structure.
	 *
	 * @return array{ items: array<int, array{ id: string, title: ?string, summary: ?string, slug: ?string }>, pagination: array }
	 */
	public function getProjects(int $page = 1, int $perPage = 8, string $q = ''): array
	{
		try {
			$conn = $this->doctrine->getConnection();

			if ($perPage <= 0) {
				$sqlAll = 'SELECT id, title, summary, slug FROM projects ORDER BY id DESC';
				$rows = $conn->executeQuery($sqlAll)->fetchAllAssociative();

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
					'pagination' => [
						'total' => count($items),
						'current_page' => 1,
						'per_page' => 0,
						'total_pages' => 1,
					],
				];
			}

			$page = max(1, $page);

			if ($q !== '') {
				$countSql = 'SELECT COUNT(*) FROM projects WHERE title ILIKE :q OR summary ILIKE :q';
				$total = (int) $conn->executeQuery($countSql, ['q' => '%' . $q . '%'])->fetchOne();
			} else {
				$total = (int) $conn->executeQuery('SELECT COUNT(*) FROM projects')->fetchOne();
			}
			$totalPages = (int) max(1, ceil($total / $perPage));
			if ($page > $totalPages) {
				$page = $totalPages;
			}

			$offset = ($page - 1) * $perPage;

			if ($q !== '') {
				$sql = 'SELECT id, title, summary, slug FROM projects WHERE title ILIKE :q OR summary ILIKE :q ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
				$result = $conn->executeQuery($sql, ['q' => '%' . $q . '%']);
			} else {
				$sql = 'SELECT id, title, summary, slug FROM projects ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
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
				'pagination' => [
					'total' => $total,
					'current_page' => $page,
					'per_page' => $perPage,
					'total_pages' => $totalPages,
				],
			];
		} catch (\Throwable $e) {
			$this->logger->error('ProjectsViewService::getProjects failed', [
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
			]);
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

