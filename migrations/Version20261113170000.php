<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113170000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE statuses ADD application_accept_enabled BOOLEAN DEFAULT TRUE NOT NULL, ADD application_reject_enabled BOOLEAN DEFAULT TRUE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE statuses DROP application_accept_enabled, DROP application_reject_enabled');
    }
}
