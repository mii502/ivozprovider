<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Create SuspensionLogs table for audit trail of company/DDI suspension events
 */
final class Version20260117000002 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Create SuspensionLogs table for suspension audit trail';
    }

    public function up(Schema $schema): void
    {
        // Create the SuspensionLogs table
        $this->addSql('
            CREATE TABLE SuspensionLogs (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                companyId INT UNSIGNED NOT NULL,
                ddiId INT UNSIGNED DEFAULT NULL,
                action VARCHAR(20) NOT NULL COMMENT \'enum:suspend|unsuspend|suspend_ddi|unsuspend_ddi\',
                reason TEXT DEFAULT NULL,
                createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_suspension_company (companyId),
                INDEX idx_suspension_created (createdAt),
                PRIMARY KEY(id),
                CONSTRAINT fk_suspensionlog_company FOREIGN KEY (companyId) REFERENCES Companies(id) ON DELETE CASCADE,
                CONSTRAINT fk_suspensionlog_ddi FOREIGN KEY (ddiId) REFERENCES DDIs(id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE SuspensionLogs');
    }
}
