<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Add WHMCS invoice sync fields to Invoices table
 *
 * This migration adds fields required for the ivozprovider-invoice-infrastructure
 * module that enables bidirectional invoice sync between IvozProvider and WHMCS.
 *
 * Fields added:
 * - whmcsInvoiceId: Links to WHMCS invoice for payment tracking
 * - syncStatus: Tracks sync state (not_applicable|pending|synced|failed)
 * - whmcsSyncedAt: Timestamp when invoice was synced to WHMCS
 * - whmcsPaidAt: Timestamp when payment was received via WHMCS webhook
 * - syncError: Error message if sync failed
 * - syncAttempts: Counter for retry logic
 * - invoiceType: Discriminator (standard|did_purchase|did_renewal|balance_topup)
 * - ddiId: Reference to DDI for DID purchase/renewal invoices
 */
final class Version20260115100000 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add WHMCS invoice sync fields to Invoices table';
    }

    public function up(Schema $schema): void
    {
        // Add WHMCS sync fields
        $this->addSql('ALTER TABLE Invoices ADD COLUMN whmcsInvoiceId INT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE Invoices ADD COLUMN syncStatus VARCHAR(20) DEFAULT \'not_applicable\'');
        $this->addSql('ALTER TABLE Invoices ADD COLUMN whmcsSyncedAt DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE Invoices ADD COLUMN whmcsPaidAt DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE Invoices ADD COLUMN syncError TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE Invoices ADD COLUMN syncAttempts INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE Invoices ADD COLUMN invoiceType VARCHAR(50) NOT NULL DEFAULT \'standard\'');
        $this->addSql('ALTER TABLE Invoices ADD COLUMN ddiId INT UNSIGNED DEFAULT NULL');

        // Add indexes for common queries
        $this->addSql('CREATE INDEX idx_invoice_whmcs_id ON Invoices (whmcsInvoiceId)');
        $this->addSql('CREATE INDEX idx_invoice_sync_status ON Invoices (syncStatus)');
        $this->addSql('CREATE INDEX idx_invoice_type ON Invoices (invoiceType)');

        // Add foreign key for DDI relation
        $this->addSql('ALTER TABLE Invoices ADD CONSTRAINT FK_invoice_ddi FOREIGN KEY (ddiId) REFERENCES DDIs(id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign key first
        $this->addSql('ALTER TABLE Invoices DROP FOREIGN KEY FK_invoice_ddi');

        // Remove indexes
        $this->addSql('DROP INDEX idx_invoice_whmcs_id ON Invoices');
        $this->addSql('DROP INDEX idx_invoice_sync_status ON Invoices');
        $this->addSql('DROP INDEX idx_invoice_type ON Invoices');

        // Remove columns
        $this->addSql('ALTER TABLE Invoices DROP COLUMN whmcsInvoiceId');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN syncStatus');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN whmcsSyncedAt');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN whmcsPaidAt');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN syncError');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN syncAttempts');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN invoiceType');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN ddiId');
    }
}
