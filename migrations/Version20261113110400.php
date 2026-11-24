<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113110400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associate projects with skills for public project listings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project_skills (project_id UUID NOT NULL, skill_id UUID NOT NULL, PRIMARY KEY(project_id, skill_id))');
        $this->addSql('CREATE INDEX IDX_PROJECT_SKILLS_SKILL ON project_skills (skill_id)');
        $this->addSql('ALTER TABLE project_skills ADD CONSTRAINT FK_PROJECT_SKILLS_PROJECT FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_skills ADD CONSTRAINT FK_PROJECT_SKILLS_SKILL FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_skills DROP CONSTRAINT FK_PROJECT_SKILLS_PROJECT');
        $this->addSql('ALTER TABLE project_skills DROP CONSTRAINT FK_PROJECT_SKILLS_SKILL');
        $this->addSql('DROP TABLE project_skills');
    }
}
