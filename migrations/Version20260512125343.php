<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512125343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account_settings (id UUID NOT NULL, language VARCHAR(5) NOT NULL, email_notifications_enabled BOOLEAN NOT NULL, profile_visibility VARCHAR(10) NOT NULL, theme VARCHAR(15) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9D8B42739B6B5FBA ON account_settings (account_id)');
        $this->addSql('CREATE TABLE accounts (id UUID NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAC89EACF85E0677 ON accounts (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAC89EACE7927C74 ON accounts (email)');
        $this->addSql('CREATE INDEX IDX_CAC89EAC6BF700BD ON accounts (status_id)');
        $this->addSql('CREATE TABLE admin_roles (id UUID NOT NULL, name VARCHAR(255) NOT NULL, permissions_mask BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1614D53D5E237E06 ON admin_roles (name)');
        $this->addSql('CREATE TABLE admins (id UUID NOT NULL, is_super_admin BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, admin_role_id UUID NOT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A2E0150F9B6B5FBA ON admins (account_id)');
        $this->addSql('CREATE INDEX IDX_A2E0150F123FA025 ON admins (admin_role_id)');
        $this->addSql('CREATE INDEX IDX_A2E0150F6BF700BD ON admins (status_id)');
        $this->addSql('CREATE TABLE audit_logs (id UUID NOT NULL, actor_username VARCHAR(255) NOT NULL, actor_email VARCHAR(255) NOT NULL, action VARCHAR(255) NOT NULL, entity_type VARCHAR(255) NOT NULL, entity_id VARCHAR(255) NOT NULL, old_values_json JSON NOT NULL, new_values_json JSON NOT NULL, request_ip VARCHAR(50) NOT NULL, user_agent TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_account_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D62F2858A9474AA9 ON audit_logs (actor_account_id)');
        $this->addSql('CREATE TABLE availabilities (id UUID NOT NULL, availability_type VARCHAR(25) NOT NULL, hours_per_week INT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, profile_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D7FC41EFCCFA12B8 ON availabilities (profile_id)');
        $this->addSql('CREATE TABLE notifications (id UUID NOT NULL, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, message TEXT NOT NULL, route TEXT DEFAULT NULL, is_read BOOLEAN NOT NULL, data_json JSON DEFAULT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, account_id UUID NOT NULL, sender_account_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6000B0D39B6B5FBA ON notifications (account_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D3CFEF0177 ON notifications (sender_account_id)');
        $this->addSql('CREATE TABLE org_members (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, org_id UUID NOT NULL, account_id UUID NOT NULL, org_role_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_36DF8631F4837C1B ON org_members (org_id)');
        $this->addSql('CREATE INDEX IDX_36DF86319B6B5FBA ON org_members (account_id)');
        $this->addSql('CREATE INDEX IDX_36DF8631A57C7662 ON org_members (org_role_id)');
        $this->addSql('CREATE TABLE org_roles (id UUID NOT NULL, name VARCHAR(255) NOT NULL, permissions_mask BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, org_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4873DDA5F4837C1B ON org_roles (org_id)');
        $this->addSql('CREATE TABLE organisations (id UUID NOT NULL, name VARCHAR(255) NOT NULL, logo_url TEXT DEFAULT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, website_url TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D7E459AC989D9B62 ON organisations (slug)');
        $this->addSql('CREATE INDEX IDX_D7E459AC6BF700BD ON organisations (status_id)');
        $this->addSql('CREATE TABLE package_tasks (id UUID NOT NULL, slug VARCHAR(255) NOT NULL, due_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, priority VARCHAR(25) NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, work_package_id UUID NOT NULL, status_id UUID NOT NULL, assigned_profile_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C55C6270EF2F062C ON package_tasks (work_package_id)');
        $this->addSql('CREATE INDEX IDX_C55C62706BF700BD ON package_tasks (status_id)');
        $this->addSql('CREATE INDEX IDX_C55C6270F45B71C9 ON package_tasks (assigned_profile_id)');
        $this->addSql('CREATE TABLE profile_experiences (id UUID NOT NULL, organisation_name VARCHAR(255) NOT NULL, job_title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, is_current BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, profile_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_971F480ECCFA12B8 ON profile_experiences (profile_id)');
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
        $this->addSql('CREATE TABLE project_applications (id UUID NOT NULL, motivation TEXT DEFAULT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, review TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, project_id UUID NOT NULL, profile_id UUID NOT NULL, status_id UUID NOT NULL, reviewer_profile_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DD6E8293166D1F9C ON project_applications (project_id)');
        $this->addSql('CREATE INDEX IDX_DD6E8293CCFA12B8 ON project_applications (profile_id)');
        $this->addSql('CREATE INDEX IDX_DD6E82936BF700BD ON project_applications (status_id)');
        $this->addSql('CREATE INDEX IDX_DD6E82931379FADA ON project_applications (reviewer_profile_id)');
        $this->addSql('CREATE TABLE project_participants (id UUID NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, left_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, project_id UUID NOT NULL, profile_id UUID NOT NULL, role_id UUID DEFAULT NULL, status_id UUID NOT NULL, application_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5BCE94F1166D1F9C ON project_participants (project_id)');
        $this->addSql('CREATE INDEX IDX_5BCE94F1CCFA12B8 ON project_participants (profile_id)');
        $this->addSql('CREATE INDEX IDX_5BCE94F1D60322AC ON project_participants (role_id)');
        $this->addSql('CREATE INDEX IDX_5BCE94F16BF700BD ON project_participants (status_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5BCE94F13E030ACD ON project_participants (application_id)');
        $this->addSql('CREATE TABLE project_roles (id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, permissions_mask BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A56B2759166D1F9C ON project_roles (project_id)');
        $this->addSql('CREATE TABLE project_updates (id UUID NOT NULL, title VARCHAR(255) NOT NULL, content TEXT DEFAULT NULL, is_public BOOLEAN NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, project_id UUID NOT NULL, author_profile_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D3561F55166D1F9C ON project_updates (project_id)');
        $this->addSql('CREATE INDEX IDX_D3561F558EF36E03 ON project_updates (author_profile_id)');
        $this->addSql('CREATE TABLE projects (id UUID NOT NULL, visibility VARCHAR(25) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(350) NOT NULL, summary VARCHAR(500) NOT NULL, description TEXT NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, capacity INT NOT NULL, remote_possible BOOLEAN NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, owner_account_id UUID NOT NULL, owner_org_id UUID NOT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C93B3A4989D9B62 ON projects (slug)');
        $this->addSql('CREATE INDEX IDX_5C93B3A4C901C6FF ON projects (owner_account_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A451795045 ON projects (owner_org_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A46BF700BD ON projects (status_id)');
        $this->addSql('CREATE TABLE reset_password_tokens (id UUID NOT NULL, token_hash VARCHAR(255) NOT NULL, is_used BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F045D5AAB3BC57DA ON reset_password_tokens (token_hash)');
        $this->addSql('CREATE INDEX IDX_F045D5AA9B6B5FBA ON reset_password_tokens (account_id)');
        $this->addSql('CREATE TABLE reviews (id UUID NOT NULL, message TEXT DEFAULT NULL, is_public BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, profile_id UUID NOT NULL, reviewer_profile_id UUID NOT NULL, project_id UUID NOT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6970EB0FCCFA12B8 ON reviews (profile_id)');
        $this->addSql('CREATE INDEX IDX_6970EB0F1379FADA ON reviews (reviewer_profile_id)');
        $this->addSql('CREATE INDEX IDX_6970EB0F166D1F9C ON reviews (project_id)');
        $this->addSql('CREATE INDEX IDX_6970EB0F6BF700BD ON reviews (status_id)');
        $this->addSql('CREATE TABLE skills (id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D53116705E237E06 ON skills (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5311670989D9B62 ON skills (slug)');
        $this->addSql('CREATE TABLE statuses (id UUID NOT NULL, name VARCHAR(255) NOT NULL, colour_hex VARCHAR(9) NOT NULL, scope VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE system_logs (id UUID NOT NULL, code SMALLINT NOT NULL, level VARCHAR(25) NOT NULL, message TEXT NOT NULL, route TEXT DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, user_agent TEXT DEFAULT NULL, context_json JSON NOT NULL, request_ip VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE verify_email_tokens (id UUID NOT NULL, email_to_verify VARCHAR(255) NOT NULL, token_hash VARCHAR(255) NOT NULL, is_used BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CE8B2C519B6B5FBA ON verify_email_tokens (account_id)');
        $this->addSql('CREATE TABLE work_packages (id UUID NOT NULL, slug VARCHAR(255) NOT NULL, due_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, project_id UUID NOT NULL, status_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4E004AFB166D1F9C ON work_packages (project_id)');
        $this->addSql('CREATE INDEX IDX_4E004AFB6BF700BD ON work_packages (status_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE account_settings ADD CONSTRAINT FK_9D8B42739B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EAC6BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE admins ADD CONSTRAINT FK_A2E0150F9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE admins ADD CONSTRAINT FK_A2E0150F123FA025 FOREIGN KEY (admin_role_id) REFERENCES admin_roles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE admins ADD CONSTRAINT FK_A2E0150F6BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A9474AA9 FOREIGN KEY (actor_account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE availabilities ADD CONSTRAINT FK_D7FC41EFCCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D39B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3CFEF0177 FOREIGN KEY (sender_account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_members ADD CONSTRAINT FK_36DF8631F4837C1B FOREIGN KEY (org_id) REFERENCES organisations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_members ADD CONSTRAINT FK_36DF86319B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_members ADD CONSTRAINT FK_36DF8631A57C7662 FOREIGN KEY (org_role_id) REFERENCES org_roles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE org_roles ADD CONSTRAINT FK_4873DDA5F4837C1B FOREIGN KEY (org_id) REFERENCES organisations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organisations ADD CONSTRAINT FK_D7E459AC6BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE package_tasks ADD CONSTRAINT FK_C55C6270EF2F062C FOREIGN KEY (work_package_id) REFERENCES work_packages (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE package_tasks ADD CONSTRAINT FK_C55C62706BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE package_tasks ADD CONSTRAINT FK_C55C6270F45B71C9 FOREIGN KEY (assigned_profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_experiences ADD CONSTRAINT FK_971F480ECCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills ADD CONSTRAINT FK_BD1B7117CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills ADD CONSTRAINT FK_BD1B71175585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills_interests ADD CONSTRAINT FK_C7F1EAEDCCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_skills_interests ADD CONSTRAINT FK_C7F1EAED5585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_social_links ADD CONSTRAINT FK_2CBA54F7CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profiles ADD CONSTRAINT FK_8B3085309B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_applications ADD CONSTRAINT FK_DD6E8293166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_applications ADD CONSTRAINT FK_DD6E8293CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_applications ADD CONSTRAINT FK_DD6E82936BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_applications ADD CONSTRAINT FK_DD6E82931379FADA FOREIGN KEY (reviewer_profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_participants ADD CONSTRAINT FK_5BCE94F1166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_participants ADD CONSTRAINT FK_5BCE94F1CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_participants ADD CONSTRAINT FK_5BCE94F1D60322AC FOREIGN KEY (role_id) REFERENCES project_roles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_participants ADD CONSTRAINT FK_5BCE94F16BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_participants ADD CONSTRAINT FK_5BCE94F13E030ACD FOREIGN KEY (application_id) REFERENCES project_applications (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_roles ADD CONSTRAINT FK_A56B2759166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_updates ADD CONSTRAINT FK_D3561F55166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_updates ADD CONSTRAINT FK_D3561F558EF36E03 FOREIGN KEY (author_profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4C901C6FF FOREIGN KEY (owner_account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A451795045 FOREIGN KEY (owner_org_id) REFERENCES organisations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A46BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reset_password_tokens ADD CONSTRAINT FK_F045D5AA9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0FCCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F1379FADA FOREIGN KEY (reviewer_profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F6BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE verify_email_tokens ADD CONSTRAINT FK_CE8B2C519B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_packages ADD CONSTRAINT FK_4E004AFB166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_packages ADD CONSTRAINT FK_4E004AFB6BF700BD FOREIGN KEY (status_id) REFERENCES statuses (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account_settings DROP CONSTRAINT FK_9D8B42739B6B5FBA');
        $this->addSql('ALTER TABLE accounts DROP CONSTRAINT FK_CAC89EAC6BF700BD');
        $this->addSql('ALTER TABLE admins DROP CONSTRAINT FK_A2E0150F9B6B5FBA');
        $this->addSql('ALTER TABLE admins DROP CONSTRAINT FK_A2E0150F123FA025');
        $this->addSql('ALTER TABLE admins DROP CONSTRAINT FK_A2E0150F6BF700BD');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858A9474AA9');
        $this->addSql('ALTER TABLE availabilities DROP CONSTRAINT FK_D7FC41EFCCFA12B8');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D39B6B5FBA');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3CFEF0177');
        $this->addSql('ALTER TABLE org_members DROP CONSTRAINT FK_36DF8631F4837C1B');
        $this->addSql('ALTER TABLE org_members DROP CONSTRAINT FK_36DF86319B6B5FBA');
        $this->addSql('ALTER TABLE org_members DROP CONSTRAINT FK_36DF8631A57C7662');
        $this->addSql('ALTER TABLE org_roles DROP CONSTRAINT FK_4873DDA5F4837C1B');
        $this->addSql('ALTER TABLE organisations DROP CONSTRAINT FK_D7E459AC6BF700BD');
        $this->addSql('ALTER TABLE package_tasks DROP CONSTRAINT FK_C55C6270EF2F062C');
        $this->addSql('ALTER TABLE package_tasks DROP CONSTRAINT FK_C55C62706BF700BD');
        $this->addSql('ALTER TABLE package_tasks DROP CONSTRAINT FK_C55C6270F45B71C9');
        $this->addSql('ALTER TABLE profile_experiences DROP CONSTRAINT FK_971F480ECCFA12B8');
        $this->addSql('ALTER TABLE profile_skills DROP CONSTRAINT FK_BD1B7117CCFA12B8');
        $this->addSql('ALTER TABLE profile_skills DROP CONSTRAINT FK_BD1B71175585C142');
        $this->addSql('ALTER TABLE profile_skills_interests DROP CONSTRAINT FK_C7F1EAEDCCFA12B8');
        $this->addSql('ALTER TABLE profile_skills_interests DROP CONSTRAINT FK_C7F1EAED5585C142');
        $this->addSql('ALTER TABLE profile_social_links DROP CONSTRAINT FK_2CBA54F7CCFA12B8');
        $this->addSql('ALTER TABLE profiles DROP CONSTRAINT FK_8B3085309B6B5FBA');
        $this->addSql('ALTER TABLE project_applications DROP CONSTRAINT FK_DD6E8293166D1F9C');
        $this->addSql('ALTER TABLE project_applications DROP CONSTRAINT FK_DD6E8293CCFA12B8');
        $this->addSql('ALTER TABLE project_applications DROP CONSTRAINT FK_DD6E82936BF700BD');
        $this->addSql('ALTER TABLE project_applications DROP CONSTRAINT FK_DD6E82931379FADA');
        $this->addSql('ALTER TABLE project_participants DROP CONSTRAINT FK_5BCE94F1166D1F9C');
        $this->addSql('ALTER TABLE project_participants DROP CONSTRAINT FK_5BCE94F1CCFA12B8');
        $this->addSql('ALTER TABLE project_participants DROP CONSTRAINT FK_5BCE94F1D60322AC');
        $this->addSql('ALTER TABLE project_participants DROP CONSTRAINT FK_5BCE94F16BF700BD');
        $this->addSql('ALTER TABLE project_participants DROP CONSTRAINT FK_5BCE94F13E030ACD');
        $this->addSql('ALTER TABLE project_roles DROP CONSTRAINT FK_A56B2759166D1F9C');
        $this->addSql('ALTER TABLE project_updates DROP CONSTRAINT FK_D3561F55166D1F9C');
        $this->addSql('ALTER TABLE project_updates DROP CONSTRAINT FK_D3561F558EF36E03');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A4C901C6FF');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A451795045');
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A46BF700BD');
        $this->addSql('ALTER TABLE reset_password_tokens DROP CONSTRAINT FK_F045D5AA9B6B5FBA');
        $this->addSql('ALTER TABLE reviews DROP CONSTRAINT FK_6970EB0FCCFA12B8');
        $this->addSql('ALTER TABLE reviews DROP CONSTRAINT FK_6970EB0F1379FADA');
        $this->addSql('ALTER TABLE reviews DROP CONSTRAINT FK_6970EB0F166D1F9C');
        $this->addSql('ALTER TABLE reviews DROP CONSTRAINT FK_6970EB0F6BF700BD');
        $this->addSql('ALTER TABLE verify_email_tokens DROP CONSTRAINT FK_CE8B2C519B6B5FBA');
        $this->addSql('ALTER TABLE work_packages DROP CONSTRAINT FK_4E004AFB166D1F9C');
        $this->addSql('ALTER TABLE work_packages DROP CONSTRAINT FK_4E004AFB6BF700BD');
        $this->addSql('DROP TABLE account_settings');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE admin_roles');
        $this->addSql('DROP TABLE admins');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE availabilities');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE org_members');
        $this->addSql('DROP TABLE org_roles');
        $this->addSql('DROP TABLE organisations');
        $this->addSql('DROP TABLE package_tasks');
        $this->addSql('DROP TABLE profile_experiences');
        $this->addSql('DROP TABLE profile_skills');
        $this->addSql('DROP TABLE profile_skills_interests');
        $this->addSql('DROP TABLE profile_social_links');
        $this->addSql('DROP TABLE profiles');
        $this->addSql('DROP TABLE project_applications');
        $this->addSql('DROP TABLE project_participants');
        $this->addSql('DROP TABLE project_roles');
        $this->addSql('DROP TABLE project_updates');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE reset_password_tokens');
        $this->addSql('DROP TABLE reviews');
        $this->addSql('DROP TABLE skills');
        $this->addSql('DROP TABLE statuses');
        $this->addSql('DROP TABLE system_logs');
        $this->addSql('DROP TABLE verify_email_tokens');
        $this->addSql('DROP TABLE work_packages');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
