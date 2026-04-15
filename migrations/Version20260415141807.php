<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415141807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accounts ADD is_Admin BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE accounts ADD last_login TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE projects ADD status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE projects ADD name VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accounts DROP is_Admin');
        $this->addSql('ALTER TABLE accounts DROP last_login');
        $this->addSql('ALTER TABLE projects DROP status');
        $this->addSql('ALTER TABLE projects DROP name');
    }
}
