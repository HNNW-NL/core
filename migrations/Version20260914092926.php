<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914092926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availabilities ADD valid_from DATE NOT NULL');
        $this->addSql('ALTER TABLE availabilities ADD valid_until DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE availabilities ADD day_of_week VARCHAR(25) NOT NULL');
        $this->addSql('ALTER TABLE availabilities ADD start_time TIME(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE availabilities ADD end_time TIME(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE availabilities DROP hours_per_week');
        $this->addSql('ALTER TABLE availabilities DROP start_date');
        $this->addSql('ALTER TABLE availabilities DROP end_date');
        $this->addSql('ALTER TABLE availabilities ALTER availability_type TYPE VARCHAR(35)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availabilities ADD hours_per_week INT NOT NULL');
        $this->addSql('ALTER TABLE availabilities ADD start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE availabilities ADD end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE availabilities DROP valid_from');
        $this->addSql('ALTER TABLE availabilities DROP valid_until');
        $this->addSql('ALTER TABLE availabilities DROP day_of_week');
        $this->addSql('ALTER TABLE availabilities DROP start_time');
        $this->addSql('ALTER TABLE availabilities DROP end_time');
        $this->addSql('ALTER TABLE availabilities ALTER availability_type TYPE VARCHAR(25)');
    }
}
