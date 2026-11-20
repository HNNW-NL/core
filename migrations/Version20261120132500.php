<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120132500 extends AbstractMigration
{
    private const STATUSES = [
        'draft' => '019aab19-3f00-7000-8000-000000000001',
        'published' => '019aab19-3f00-7000-8000-000000000002',
        'applications_closed' => '019aab19-3f00-7000-8000-000000000003',
        'completed' => '019aab19-3f00-7000-8000-000000000004',
        'archived' => '019aab19-3f00-7000-8000-000000000005',
    ];

    public function getDescription(): string
    {
        return 'Seed project lifecycle statuses and the active participant status.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::STATUSES as $name => $id) {
            $this->insertStatus($id, $name, 'project');
        }
        $this->insertStatus('019aab19-3f00-7000-8000-000000000006', 'active', 'ProjectParticipant');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('These statuses may be referenced by existing projects and participants.');
    }

    private function insertStatus(string $id, string $name, string $scope): void
    {
        $this->addSql(
            "INSERT INTO statuses (id, name, scope, colour_hex)
             SELECT :id, :name, :scope, '#64748B'
             WHERE NOT EXISTS (SELECT 1 FROM statuses WHERE name = :name AND scope = :scope)",
            ['id' => $id, 'name' => $name, 'scope' => $scope],
        );
    }
}
