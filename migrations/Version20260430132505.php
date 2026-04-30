<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430132505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accounts (id UUID NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAC89EACF85E0677 ON accounts (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAC89EACE7927C74 ON accounts (email)');
        $this->addSql('CREATE INDEX IDX_CAC89EAC6BF700BD ON accounts (status_id)');
        $this->addSql('CREATE TABLE audit_logs (id UUID NOT NULL, actor_username VARCHAR(255) NOT NULL, actor_email VARCHAR(255) NOT NULL, action VARCHAR(255) NOT NULL, entity_type VARCHAR(255) NOT NULL, entity_id VARCHAR(255) NOT NULL, old_values_json JSON NOT NULL, new_values_json JSON NOT NULL, request_ip VARCHAR(50) NOT NULL, user_agent TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_account_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D62F2858A9474AA9 ON audit_logs (actor_account_id)');
        $this->addSql('CREATE TABLE org_members (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, org_id UUID NOT NULL, account_id UUID NOT NULL, org_role_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_36DF8631F4837C1B ON org_members (org_id)');
        $this->addSql('CREATE INDEX IDX_36DF86319B6B5FBA ON org_members (account_id)');
        $this->addSql('CREATE INDEX IDX_36DF8631A57C7662 ON org_members (org_role_id)');
        $this->addSql('CREATE TABLE org_roles (id UUID NOT NULL, name VARCHAR(255) NOT NULL, permissions_mask BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, org_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4873DDA5F4837C1B ON org_roles (org_id)');
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, name VARCHAR(255) NOT NULL, logo_url TEXT DEFAULT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, website_url TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7F989D9B62 ON organizations (slug)');
        $this->addSql('CREATE INDEX IDX_427C1C7F6BF700BD ON organizations (status_id)');
        $this->addSql('CREATE TABLE profile_skills (id UUID NOT NULL, proficiency_level VARCHAR(50) NOT NULL, is_featured BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, profile_id UUID NOT NULL, skill_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BD1B7117CCFA12B8 ON profile_skills (profile_id)');
        $this->addSql('CREATE INDEX IDX_BD1B71175585C142 ON profile_skills (skill_id)');
        $this->addSql('CREATE TABLE profile_skills_interests (id UUID NOT NULL, is_featured BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, profile_id UUID NOT NULL, skill_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C7F1EAEDCCFA12B8 ON profile_skills_interests (profile_id)');
        $this->addSql('CREATE INDEX IDX_C7F1EAED5585C142 ON profile_skills_interests (skill_id)');
        $this->addSql('CREATE TABLE profile_social_links (id UUID NOT NULL, label VARCHAR(255) NOT NULL, url TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, profile_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2CBA54F7CCFA12B8 ON profile_social_links (profile_id)');
        $this->addSql('CREATE TABLE profiles (id UUID NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, avatar_url TEXT NOT NULL, description TEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, is_online BOOLEAN NOT NULL, points INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B3085309B6B5FBA ON profiles (account_id)');
        $this->addSql('CREATE TABLE skills (id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D53116705E237E06 ON skills (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5311670989D9B62 ON skills (slug)');
        $this->addSql('CREATE TABLE statuses (id UUID NOT NULL, name VARCHAR(255) NOT NULL, colour_hex VARCHAR(9) NOT NULL, scope VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE system_logs (id UUID NOT NULL, code SMALLINT NOT NULL, level VARCHAR(25) NOT NULL, message TEXT NOT NULL, route TEXT DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, user_agent TEXT DEFAULT NULL, context_json JSON NOT NULL, request_ip VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EAC6BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A9474AA9 FOREIGN KEY (actor_account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_members ADD CONSTRAINT FK_36DF8631F4837C1B FOREIGN KEY (org_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_members ADD CONSTRAINT FK_36DF86319B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_members ADD CONSTRAINT FK_36DF8631A57C7662 FOREIGN KEY (org_role_id) REFERENCES org_roles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_roles ADD CONSTRAINT FK_4873DDA5F4837C1B FOREIGN KEY (org_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organizations ADD CONSTRAINT FK_427C1C7F6BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills ADD CONSTRAINT FK_BD1B7117CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills ADD CONSTRAINT FK_BD1B71175585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills_interests ADD CONSTRAINT FK_C7F1EAEDCCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills_interests ADD CONSTRAINT FK_C7F1EAED5585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_social_links ADD CONSTRAINT FK_2CBA54F7CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profiles ADD CONSTRAINT FK_8B3085309B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accounts DROP CONSTRAINT FK_CAC89EAC6BF700BD');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858A9474AA9');
        $this->addSql('ALTER TABLE org_members DROP CONSTRAINT FK_36DF8631F4837C1B');
        $this->addSql('ALTER TABLE org_members DROP CONSTRAINT FK_36DF86319B6B5FBA');
        $this->addSql('ALTER TABLE org_members DROP CONSTRAINT FK_36DF8631A57C7662');
        $this->addSql('ALTER TABLE org_roles DROP CONSTRAINT FK_4873DDA5F4837C1B');
        $this->addSql('ALTER TABLE organizations DROP CONSTRAINT FK_427C1C7F6BF700BD');
        $this->addSql('ALTER TABLE profile_skills DROP CONSTRAINT FK_BD1B7117CCFA12B8');
        $this->addSql('ALTER TABLE profile_skills DROP CONSTRAINT FK_BD1B71175585C142');
        $this->addSql('ALTER TABLE profile_skills_interests DROP CONSTRAINT FK_C7F1EAEDCCFA12B8');
        $this->addSql('ALTER TABLE profile_skills_interests DROP CONSTRAINT FK_C7F1EAED5585C142');
        $this->addSql('ALTER TABLE profile_social_links DROP CONSTRAINT FK_2CBA54F7CCFA12B8');
        $this->addSql('ALTER TABLE profiles DROP CONSTRAINT FK_8B3085309B6B5FBA');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE org_members');
        $this->addSql('DROP TABLE org_roles');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE profile_skills');
        $this->addSql('DROP TABLE profile_skills_interests');
        $this->addSql('DROP TABLE profile_social_links');
        $this->addSql('DROP TABLE profiles');
        $this->addSql('DROP TABLE skills');
        $this->addSql('DROP TABLE statuses');
        $this->addSql('DROP TABLE system_logs');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
