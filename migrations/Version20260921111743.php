<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921111743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE events (id UUID NOT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, subtitle VARCHAR(255) DEFAULT NULL, start_datetime TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_datetime TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, sign_up_url VARCHAR(2048) DEFAULT NULL, contact_email VARCHAR(254) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, org_id UUID NOT NULL, author_account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5387574A989D9B62 ON events (slug)');
        $this->addSql('CREATE INDEX IDX_5387574AF4837C1B ON events (org_id)');
        $this->addSql('CREATE INDEX IDX_5387574AD9622301 ON events (author_account_id)');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AF4837C1B FOREIGN KEY (org_id) REFERENCES organisations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AD9622301 FOREIGN KEY (author_account_id) REFERENCES accounts (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574AF4837C1B');
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574AD9622301');
        $this->addSql('DROP TABLE events');
    }
}
