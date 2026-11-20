<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional responsible profile to work packages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_packages ADD responsible_profile_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_WORK_PACKAGE_RESPONSIBLE_PROFILE ON work_packages (responsible_profile_id)');
        $this->addSql('ALTER TABLE work_packages ADD CONSTRAINT FK_WORK_PACKAGE_RESPONSIBLE_PROFILE FOREIGN KEY (responsible_profile_id) REFERENCES profiles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_packages DROP CONSTRAINT FK_WORK_PACKAGE_RESPONSIBLE_PROFILE');
        $this->addSql('DROP INDEX IDX_WORK_PACKAGE_RESPONSIBLE_PROFILE');
        $this->addSql('ALTER TABLE work_packages DROP responsible_profile_id');
    }
}
