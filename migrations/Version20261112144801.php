<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112144801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // New tables

        $this->addSql('CREATE TABLE jobs (id UUID NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT NOT NULL, employment_type VARCHAR(40) NOT NULL, work_mode VARCHAR(30) NOT NULL, application_url TEXT NOT NULL, location VARCHAR(255) DEFAULT NULL, salary_min INT DEFAULT NULL, salary_max INT DEFAULT NULL, salary_currency VARCHAR(3) DEFAULT NULL, hours_per_week_min INT DEFAULT NULL, hours_per_week_max INT DEFAULT NULL, requirements TEXT DEFAULT NULL, benefits TEXT DEFAULT NULL, deadline DATE DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_url TEXT DEFAULT NULL, source_name VARCHAR(120) DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organisation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A8936DC59E6B1585 ON jobs (organisation_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_JOBS_SLUG ON jobs (slug)');

        $this->addSql('CREATE TABLE job_skills (id UUID NOT NULL, required BOOLEAN DEFAULT false NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, job_id UUID NOT NULL, skill_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_85353BF1BE04EA9 ON job_skills (job_id)');
        $this->addSql('CREATE INDEX IDX_85353BF15585C142 ON job_skills (skill_id)');

        $this->addSql('CREATE TABLE moderation_cases (id UUID NOT NULL, content_type VARCHAR(80) NOT NULL, content_id UUID NOT NULL, status VARCHAR(25) NOT NULL, reason TEXT DEFAULT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, submitted_by_id UUID NOT NULL, reviewed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EF41E41F79F7D87D ON moderation_cases (submitted_by_id)');
        $this->addSql('CREATE INDEX IDX_EF41E41FFC6B21F1 ON moderation_cases (reviewed_by_id)');

        $this->addSql('CREATE TABLE organisation_ownership_transfers (id UUID NOT NULL, status VARCHAR(25) NOT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organisation_id UUID NOT NULL, from_account_id UUID NOT NULL, to_account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_88C225AB9E6B1585 ON organisation_ownership_transfers (organisation_id)');
        $this->addSql('CREATE INDEX IDX_88C225ABB0CF99BD ON organisation_ownership_transfers (from_account_id)');
        $this->addSql('CREATE INDEX IDX_88C225ABBC58BDC7 ON organisation_ownership_transfers (to_account_id)');

        $this->addSql('CREATE TABLE profile_certifications (id UUID NOT NULL, name VARCHAR(255) NOT NULL, issuer VARCHAR(255) NOT NULL, issued_on DATE DEFAULT NULL, expires_on DATE DEFAULT NULL, credential_id VARCHAR(255) DEFAULT NULL, credential_url TEXT DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, profile_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A047B7C0CCFA12B8 ON profile_certifications (profile_id)');

        $this->addSql('CREATE TABLE profile_educations (id UUID NOT NULL, institution_name VARCHAR(255) NOT NULL, degree VARCHAR(255) DEFAULT NULL, field_of_study VARCHAR(255) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, description TEXT DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, profile_id UUID NOT NULL, organisation_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B46341F0CCFA12B8 ON profile_educations (profile_id)');
        $this->addSql('CREATE INDEX IDX_B46341F09E6B1585 ON profile_educations (organisation_id)');

        $this->addSql('CREATE TABLE project_skills (id UUID NOT NULL, required BOOLEAN DEFAULT false NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, project_id UUID NOT NULL, skill_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C295FD3A166D1F9C ON project_skills (project_id)');
        $this->addSql('CREATE INDEX IDX_C295FD3A5585C142 ON project_skills (skill_id)');

        $this->addSql('CREATE TABLE saved_items (id UUID NOT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, job_id UUID DEFAULT NULL, project_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8CCD32549B6B5FBA ON saved_items (account_id)');
        $this->addSql('CREATE INDEX IDX_8CCD3254BE04EA9 ON saved_items (job_id)');
        $this->addSql('CREATE INDEX IDX_8CCD3254166D1F9C ON saved_items (project_id)');

        $this->addSql('CREATE TABLE taxonomy_suggestions (id UUID NOT NULL, taxonomy_type VARCHAR(60) NOT NULL, proposed_name VARCHAR(255) NOT NULL, reason TEXT DEFAULT NULL, status VARCHAR(25) NOT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_modified TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, suggested_by_id UUID NOT NULL, reviewed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E427E9DE66290AB1 ON taxonomy_suggestions (suggested_by_id)');
        $this->addSql('CREATE INDEX IDX_E427E9DEFC6B21F1 ON taxonomy_suggestions (reviewed_by_id)');

        // Foreign keys for new tables

        $this->addSql('ALTER TABLE jobs ADD CONSTRAINT FK_A8936DC59E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisations (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE job_skills ADD CONSTRAINT FK_85353BF1BE04EA9 FOREIGN KEY (job_id) REFERENCES jobs (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE job_skills ADD CONSTRAINT FK_85353BF15585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE moderation_cases ADD CONSTRAINT FK_EF41E41F79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE moderation_cases ADD CONSTRAINT FK_EF41E41FFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES accounts (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('ALTER TABLE organisation_ownership_transfers ADD CONSTRAINT FK_88C225AB9E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organisation_ownership_transfers ADD CONSTRAINT FK_88C225ABB0CF99BD FOREIGN KEY (from_account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organisation_ownership_transfers ADD CONSTRAINT FK_88C225ABBC58BDC7 FOREIGN KEY (to_account_id) REFERENCES accounts (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE profile_certifications ADD CONSTRAINT FK_A047B7C0CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE profile_educations ADD CONSTRAINT FK_B46341F0CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profiles (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE profile_educations ADD CONSTRAINT FK_B46341F09E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisations (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('ALTER TABLE project_skills ADD CONSTRAINT FK_C295FD3A166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_skills ADD CONSTRAINT FK_C295FD3A5585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE saved_items ADD CONSTRAINT FK_8CCD32549B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE saved_items ADD CONSTRAINT FK_8CCD3254BE04EA9 FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE saved_items ADD CONSTRAINT FK_8CCD3254166D1F9C FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('ALTER TABLE taxonomy_suggestions ADD CONSTRAINT FK_E427E9DE66290AB1 FOREIGN KEY (suggested_by_id) REFERENCES accounts (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE taxonomy_suggestions ADD CONSTRAINT FK_E427E9DEFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES accounts (id) ON DELETE SET NULL NOT DEFERRABLE');

        // Existing table changes

        $this->addSql('ALTER TABLE account_settings ADD profile_public_mask BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE account_settings ADD profile_connections_mask BIGINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE account_settings DROP created_at');

        $this->addSql('ALTER TABLE accounts DROP created_at');
        $this->addSql('ALTER TABLE admin_roles DROP created_at');
        $this->addSql('ALTER TABLE admins DROP created_at');

        $this->addSql('ALTER TABLE audit_logs ADD context_json JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE audit_logs DROP created_at');

        $this->addSql('ALTER TABLE availabilities DROP created_at');
        $this->addSql('ALTER TABLE events DROP created_at');

        $this->addSql('ALTER TABLE notifications ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications DROP created_at');

        $this->addSql('ALTER TABLE org_members DROP created_at');
        $this->addSql('ALTER TABLE org_roles DROP created_at');
        $this->addSql('ALTER TABLE org_roles ALTER permissions_mask SET DEFAULT 0');
        $this->addSql('ALTER TABLE org_roles ALTER permissions_mask SET NOT NULL');

        $this->addSql('ALTER TABLE organisations ADD sector VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE organisations ADD contact_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE organisations ADD verified BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE organisations ADD owner_account_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE organisations DROP created_at');
        $this->addSql('ALTER TABLE organisations ADD CONSTRAINT FK_D7E459ACC901C6FF FOREIGN KEY (owner_account_id) REFERENCES accounts (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_D7E459ACC901C6FF ON organisations (owner_account_id)');

        $this->addSql('ALTER TABLE package_tasks DROP created_at');

        // Preserve existing organisation names

        $this->addSql('ALTER TABLE profile_experiences ADD external_organisation_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile_experiences ADD organisation_id UUID DEFAULT NULL');
        $this->addSql('UPDATE profile_experiences SET external_organisation_name = organisation_name');
        $this->addSql('ALTER TABLE profile_experiences DROP organisation_name');
        $this->addSql('ALTER TABLE profile_experiences DROP created_at');
        $this->addSql('ALTER TABLE profile_experiences ADD CONSTRAINT FK_971F480E9E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisations (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_971F480E9E6B1585 ON profile_experiences (organisation_id)');

        $this->addSql('ALTER TABLE profile_skills ADD years_of_experience SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE profile_skills DROP created_at');
        $this->addSql('ALTER TABLE profile_skills ALTER proficiency_level DROP NOT NULL');

        $this->addSql('ALTER TABLE profile_skills_interests DROP created_at');

        $this->addSql('ALTER TABLE profile_social_links ADD link_type VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile_social_links ADD sort_order INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE profile_social_links DROP created_at');

        $this->addSql('ALTER TABLE profiles ADD headline VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE profiles ADD preferred_work_mode VARCHAR(24) DEFAULT NULL');
        $this->addSql('ALTER TABLE profiles ADD open_to_opportunities BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE profiles ADD current_experience_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE profiles DROP created_at');
        $this->addSql('ALTER TABLE profiles ADD CONSTRAINT FK_8B3085305717B4C5 FOREIGN KEY (current_experience_id) REFERENCES profile_experiences (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_8B3085305717B4C5 ON profiles (current_experience_id)');

        $this->addSql('ALTER TABLE project_applications DROP created_at');
        $this->addSql('ALTER TABLE project_participants DROP created_at');
        $this->addSql('ALTER TABLE project_roles DROP created_at');
        $this->addSql('ALTER TABLE project_updates DROP created_at');

        $this->addSql('ALTER TABLE projects ADD allow_following BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE projects DROP created_at');

        $this->addSql('ALTER TABLE reset_password_tokens DROP created_at');

        $this->addSql('ALTER TABLE reviews ADD title VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE reviews ADD rating SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE reviews DROP created_at');

        $this->addSql('ALTER TABLE skills ADD is_active BOOLEAN DEFAULT true NOT NULL');

        $this->addSql('ALTER TABLE system_logs DROP created_at');
        $this->addSql('ALTER TABLE verify_email_tokens DROP created_at');
        $this->addSql('ALTER TABLE work_packages DROP created_at');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign keys from new tables

        $this->addSql('ALTER TABLE job_skills DROP CONSTRAINT FK_85353BF1BE04EA9');
        $this->addSql('ALTER TABLE job_skills DROP CONSTRAINT FK_85353BF15585C142');

        $this->addSql('ALTER TABLE jobs DROP CONSTRAINT FK_A8936DC59E6B1585');

        $this->addSql('ALTER TABLE moderation_cases DROP CONSTRAINT FK_EF41E41F79F7D87D');
        $this->addSql('ALTER TABLE moderation_cases DROP CONSTRAINT FK_EF41E41FFC6B21F1');

        $this->addSql('ALTER TABLE organisation_ownership_transfers DROP CONSTRAINT FK_88C225AB9E6B1585');
        $this->addSql('ALTER TABLE organisation_ownership_transfers DROP CONSTRAINT FK_88C225ABB0CF99BD');
        $this->addSql('ALTER TABLE organisation_ownership_transfers DROP CONSTRAINT FK_88C225ABBC58BDC7');

        $this->addSql('ALTER TABLE profile_certifications DROP CONSTRAINT FK_A047B7C0CCFA12B8');

        $this->addSql('ALTER TABLE profile_educations DROP CONSTRAINT FK_B46341F0CCFA12B8');
        $this->addSql('ALTER TABLE profile_educations DROP CONSTRAINT FK_B46341F09E6B1585');

        $this->addSql('ALTER TABLE project_skills DROP CONSTRAINT FK_C295FD3A166D1F9C');
        $this->addSql('ALTER TABLE project_skills DROP CONSTRAINT FK_C295FD3A5585C142');

        $this->addSql('ALTER TABLE saved_items DROP CONSTRAINT FK_8CCD32549B6B5FBA');
        $this->addSql('ALTER TABLE saved_items DROP CONSTRAINT FK_8CCD3254BE04EA9');
        $this->addSql('ALTER TABLE saved_items DROP CONSTRAINT FK_8CCD3254166D1F9C');

        $this->addSql('ALTER TABLE taxonomy_suggestions DROP CONSTRAINT FK_E427E9DE66290AB1');
        $this->addSql('ALTER TABLE taxonomy_suggestions DROP CONSTRAINT FK_E427E9DEFC6B21F1');

        // Remove new tables

        $this->addSql('DROP TABLE job_skills');
        $this->addSql('DROP TABLE jobs');
        $this->addSql('DROP TABLE moderation_cases');
        $this->addSql('DROP TABLE organisation_ownership_transfers');
        $this->addSql('DROP TABLE profile_certifications');
        $this->addSql('DROP TABLE profile_educations');
        $this->addSql('DROP TABLE project_skills');
        $this->addSql('DROP TABLE saved_items');
        $this->addSql('DROP TABLE taxonomy_suggestions');

        // Restore account settings

        $this->addSql('ALTER TABLE account_settings ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE account_settings DROP profile_public_mask');
        $this->addSql('ALTER TABLE account_settings DROP profile_connections_mask');

        $this->addSql('ALTER TABLE accounts ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');

        $this->addSql('ALTER TABLE admin_roles ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE admins ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');

        $this->addSql('ALTER TABLE audit_logs ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE audit_logs DROP context_json');

        $this->addSql('ALTER TABLE availabilities ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE events ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');

        $this->addSql('ALTER TABLE notifications ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE notifications DROP expires_at');

        $this->addSql('ALTER TABLE org_members ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE org_roles ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE org_roles ALTER permissions_mask DROP DEFAULT');
        $this->addSql('ALTER TABLE org_roles ALTER permissions_mask DROP NOT NULL');

        // Restore organisations

        $this->addSql('ALTER TABLE organisations DROP CONSTRAINT FK_D7E459ACC901C6FF');
        $this->addSql('DROP INDEX IDX_D7E459ACC901C6FF');

        $this->addSql('ALTER TABLE organisations ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE organisations DROP sector');
        $this->addSql('ALTER TABLE organisations DROP contact_email');
        $this->addSql('ALTER TABLE organisations DROP verified');
        $this->addSql('ALTER TABLE organisations DROP owner_account_id');

        $this->addSql('ALTER TABLE package_tasks ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');

        // Restore original experience organisation names

        $this->addSql('ALTER TABLE profile_experiences DROP CONSTRAINT FK_971F480E9E6B1585');
        $this->addSql('DROP INDEX IDX_971F480E9E6B1585');

        $this->addSql('ALTER TABLE profile_experiences ADD organisation_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE profile_experiences SET organisation_name = external_organisation_name');
        $this->addSql("UPDATE profile_experiences SET organisation_name = '' WHERE organisation_name IS NULL");
        $this->addSql('ALTER TABLE profile_experiences ALTER organisation_name SET NOT NULL');

        $this->addSql('ALTER TABLE profile_experiences ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE profile_experiences DROP external_organisation_name');
        $this->addSql('ALTER TABLE profile_experiences DROP organisation_id');

        // Restore profile skill columns

        $this->addSql('ALTER TABLE profile_skills ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE profile_skills DROP years_of_experience');
        $this->addSql('UPDATE profile_skills SET proficiency_level = 0 WHERE proficiency_level IS NULL');
        $this->addSql('ALTER TABLE profile_skills ALTER proficiency_level SET NOT NULL');

        $this->addSql('ALTER TABLE profile_skills_interests ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');

        // Restore social links

        $this->addSql('ALTER TABLE profile_social_links ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE profile_social_links DROP link_type');
        $this->addSql('ALTER TABLE profile_social_links DROP sort_order');

        // Restore profiles

        $this->addSql('ALTER TABLE profiles DROP CONSTRAINT FK_8B3085305717B4C5');
        $this->addSql('DROP INDEX IDX_8B3085305717B4C5');

        $this->addSql('ALTER TABLE profiles ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE profiles DROP headline');
        $this->addSql('ALTER TABLE profiles DROP preferred_work_mode');
        $this->addSql('ALTER TABLE profiles DROP open_to_opportunities');
        $this->addSql('ALTER TABLE profiles DROP current_experience_id');

        // Restore project-related columns

        $this->addSql('ALTER TABLE project_applications ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE project_participants ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE project_roles ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE project_updates ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');

        $this->addSql('ALTER TABLE projects ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE projects DROP allow_following');

        $this->addSql('ALTER TABLE reset_password_tokens ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');

        // Restore reviews

        $this->addSql('ALTER TABLE reviews ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE reviews DROP title');
        $this->addSql('ALTER TABLE reviews DROP rating');

        $this->addSql('ALTER TABLE skills DROP is_active');

        // Restore remaining timestamps

        $this->addSql('ALTER TABLE system_logs ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE verify_email_tokens ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE work_packages ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
