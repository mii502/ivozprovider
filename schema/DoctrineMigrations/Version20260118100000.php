<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Create DidOrders table for postpaid DID ordering workflow
 */
final class Version20260118100000 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Create DidOrders table for postpaid DID ordering with admin approval workflow';
    }

    public function up(Schema $schema): void
    {
        // Create DidOrders table
        $this->addSql('
            CREATE TABLE DidOrders (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                companyId INT UNSIGNED NOT NULL,
                ddiId INT UNSIGNED NOT NULL,
                status VARCHAR(30) DEFAULT \'pending_approval\' NOT NULL COMMENT \'[enum:pending_approval|approved|rejected|expired]\',
                requestedAt DATETIME NOT NULL,
                approvedById INT UNSIGNED DEFAULT NULL,
                approvedAt DATETIME DEFAULT NULL,
                rejectedAt DATETIME DEFAULT NULL,
                rejectionReason TEXT DEFAULT NULL,
                setupFee NUMERIC(10, 4) DEFAULT \'0.0000\' NOT NULL,
                monthlyFee NUMERIC(10, 4) DEFAULT \'0.0000\' NOT NULL,
                PRIMARY KEY(id),
                INDEX IDX_DID_ORDER_COMPANY (companyId),
                INDEX IDX_DID_ORDER_DDI (ddiId),
                INDEX IDX_DID_ORDER_STATUS (status),
                INDEX IDX_DID_ORDER_APPROVED_BY (approvedById),
                CONSTRAINT FK_DID_ORDER_COMPANY FOREIGN KEY (companyId) REFERENCES Companies (id) ON DELETE CASCADE,
                CONSTRAINT FK_DID_ORDER_DDI FOREIGN KEY (ddiId) REFERENCES DDIs (id) ON DELETE CASCADE,
                CONSTRAINT FK_DID_ORDER_APPROVED_BY FOREIGN KEY (approvedById) REFERENCES Administrators (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE DidOrders');
    }
}
