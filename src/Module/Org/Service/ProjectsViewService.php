<?php
namespace App\Module\Org\Service;

use App\Entity\Project\Project;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * ProjectsViewService
 *
 * Retrieves projects from the database for display on the projects listing page.
 * Supports search filtering and error handling with logging.
 */
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
	 * Get all projects matching an optional search query
	 *
	 * @param string $q Optional search query (searches title and summary)
	 * @return array{ items: array<int, array{ id: string, title: ?string, summary: ?string, slug: ?string }> }
	 *   Returns all matching projects or empty array on error
	 */
	public function getProjects(string $organisationId, string $q = ''): array
{
    try {
        $conn = $this->doctrine->getConnection();

        // Haal alleen projecten van de gekozen organisatie op.
        $sql = 'SELECT id, title, summary, slug
                FROM projects
                WHERE owner_org_id = :organisationId';

        $params = [
            'organisationId' => $organisationId,
        ];

        // Optioneel zoeken binnen die organisatie.
        if ($q !== '') {
            $sql .= ' AND (title ILIKE :q OR summary ILIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY id DESC';

        $result = $conn->executeQuery($sql, $params);
        $rows = $result->fetchAllAssociative();

			// Transform raw DB rows into consistent array format
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
			// Log error and return empty array to prevent template fatal errors
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

