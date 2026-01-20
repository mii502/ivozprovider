<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Add paidVia field to Invoices for Balance-First billing model
 *
 * This field tracks how an invoice was paid:
 * - NULL: unpaid
 * - 'balance': paid via Company.balance (silent billing)
 * - 'whmcs': paid via WHMCS gateway (customer-facing)
 */
final class Version20260120200000 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add paidVia field to Invoices for Balance-First billing model';
    }

    public function up(Schema $schema): void
    {
        // Add the paidVia column
        $this->addSql("ALTER TABLE Invoices ADD COLUMN paidVia VARCHAR(20) DEFAULT NULL COMMENT '[enum:balance|whmcs]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Invoices DROP COLUMN paidVia');
    }
}
