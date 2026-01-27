<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * BYON (Bring Your Own Number) Feature
 * - Creates ByonVerifications table for OTP verification tracking
 * - Adds isByon and byonVerificationId to DDIs table
 * - Adds byonMaxNumbers to Companies table
 */
final class Version20260127100000 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add BYON (Bring Your Own Number) support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        // Create ByonVerifications table
        $this->addSql('CREATE TABLE ByonVerifications (
            id INT UNSIGNED AUTO_INCREMENT NOT NULL,
            companyId INT UNSIGNED NOT NULL,
            phoneNumber VARCHAR(25) NOT NULL COMMENT \'E.164 format\',
            verificationSid VARCHAR(64) DEFAULT NULL COMMENT \'Somleng verification SID\',
            status VARCHAR(20) NOT NULL DEFAULT \'pending\' COMMENT \'[enum:pending|approved|expired|failed]\',
            attempts INT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'OTP check attempts\',
            createdAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\',
            verifiedAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime)\',
            expiresAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\',
            INDEX IDX_byon_company (companyId),
            INDEX IDX_byon_phone (phoneNumber),
            INDEX IDX_byon_status (status),
            INDEX IDX_byon_created (createdAt),
            PRIMARY KEY(id),
            CONSTRAINT FK_byon_company FOREIGN KEY (companyId) REFERENCES Companies (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add BYON columns to DDIs table
        $this->addSql('ALTER TABLE DDIs
            ADD isByon TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT \'BYON: Customer-verified number\',
            ADD byonVerificationId INT UNSIGNED DEFAULT NULL COMMENT \'Link to BYON verification record\'');

        $this->addSql('CREATE INDEX IDX_ddi_is_byon ON DDIs (isByon)');

        $this->addSql('ALTER TABLE DDIs
            ADD CONSTRAINT FK_ddi_byon_verification
            FOREIGN KEY (byonVerificationId) REFERENCES ByonVerifications (id)
            ON DELETE SET NULL');

        // Add byonMaxNumbers to Companies table
        $this->addSql('ALTER TABLE Companies
            ADD byonMaxNumbers INT UNSIGNED NOT NULL DEFAULT 10 COMMENT \'BYON: Max customer-verified numbers\'');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        // Remove foreign key and columns from DDIs
        $this->addSql('ALTER TABLE DDIs DROP FOREIGN KEY FK_ddi_byon_verification');
        $this->addSql('DROP INDEX IDX_ddi_is_byon ON DDIs');
        $this->addSql('ALTER TABLE DDIs DROP isByon, DROP byonVerificationId');

        // Remove byonMaxNumbers from Companies
        $this->addSql('ALTER TABLE Companies DROP byonMaxNumbers');

        // Drop ByonVerifications table
        $this->addSql('DROP TABLE ByonVerifications');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }
}
