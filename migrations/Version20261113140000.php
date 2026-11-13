<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add workflow statuses for work packages and normalize existing work package status assignments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO statuses (id, name, colour_hex, scope)
            SELECT gen_random_uuid(), workflow.name, workflow.colour_hex, 'work_package'
            FROM (VALUES
                ('Draft', '#64748B'), ('Planned', '#6366F1'), ('Open', '#10B981'),
                ('Assigned', '#F59E0B'), ('Closed', '#64748B'), ('Cancelled', '#EF4444')
            ) AS workflow(name, colour_hex)
            WHERE NOT EXISTS (
                SELECT 1 FROM statuses WHERE scope = 'work_package' AND name = workflow.name
            )
            SQL);

        $this->addSql(<<<'SQL'
            WITH canonical AS (
                SELECT DISTINCT ON (name) id, name
                FROM statuses
                WHERE scope = 'work_package'
                  AND name IN ('Draft', 'Planned', 'Open', 'Assigned', 'Closed', 'Cancelled')
                ORDER BY name, id
            )
            UPDATE work_packages AS package
            SET status_id = canonical.id
            FROM statuses AS previous, canonical
            WHERE package.status_id = previous.id
              AND previous.scope IN ('WorkPackage', 'work_package')
              AND lower(canonical.name) = CASE lower(previous.name)
                  WHEN 'active' THEN 'open'
                  ELSE lower(previous.name)
              END
              AND (previous.scope <> 'work_package' OR previous.name <> canonical.name)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Original work package status assignments cannot be reconstructed.');
    }
}
