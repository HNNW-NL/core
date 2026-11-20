<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120120000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM project_applications GROUP BY project_id, profile_id HAVING COUNT(*) > 1)'), 'Bestaande dubbele aanvragen moeten eerst worden beoordeeld.');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_project_application_scope ON project_applications (project_id, profile_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_project_application_scope');
    }
}
