<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Add DID Marketplace fields for inventory management and pricing
 *
 * This migration adds fields required for the ivozprovider-did-marketplace
 * module that enables DID browsing and purchase from the client portal.
 *
 * DDI table extensions:
 * - setupPrice: One-time purchase fee
 * - monthlyPrice: Monthly rental fee
 * - inventoryStatus: Lifecycle state (available|reserved|assigned|suspended|disabled)
 * - assignedAt: Timestamp when DID was assigned to a company
 * - nextRenewalAt: Next billing date for renewal
 * - reservedForCompanyId: Company holding the reservation (for postpaid orders)
 * - reservedUntil: Reservation expiry timestamp
 *
 * Brand table extension:
 * - didRenewalMode: Renewal mode (per_did|consolidated)
 *
 * Company table extension:
 * - didRenewalAnchor: Anchor date for consolidated renewal mode
 */
final class Version20260116150000 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add DID Marketplace fields for inventory management and pricing';
    }

    public function up(Schema $schema): void
    {
        // DDI table - Add pricing fields
        $this->addSql('ALTER TABLE DDIs ADD COLUMN setupPrice DECIMAL(10,4) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE DDIs ADD COLUMN monthlyPrice DECIMAL(10,4) NOT NULL DEFAULT 0');

        // DDI table - Add inventory status
        $this->addSql('ALTER TABLE DDIs ADD COLUMN inventoryStatus VARCHAR(20) NOT NULL DEFAULT \'available\'');

        // DDI table - Add assignment tracking
        $this->addSql('ALTER TABLE DDIs ADD COLUMN assignedAt DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE DDIs ADD COLUMN nextRenewalAt DATE DEFAULT NULL');

        // DDI table - Add reservation fields (for postpaid orders)
        $this->addSql('ALTER TABLE DDIs ADD COLUMN reservedForCompanyId INT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE DDIs ADD COLUMN reservedUntil DATETIME DEFAULT NULL');

        // Add indexes for marketplace queries
        $this->addSql('CREATE INDEX idx_ddi_inventory_status ON DDIs (inventoryStatus)');
        $this->addSql('CREATE INDEX idx_ddi_next_renewal ON DDIs (nextRenewalAt)');
        $this->addSql('CREATE INDEX idx_ddi_reserved_company ON DDIs (reservedForCompanyId)');

        // Add foreign key for reservation
        $this->addSql('ALTER TABLE DDIs ADD CONSTRAINT FK_ddi_reserved_company FOREIGN KEY (reservedForCompanyId) REFERENCES Companies(id) ON DELETE SET NULL');

        // Brand table - Add DID renewal mode
        $this->addSql('ALTER TABLE Brands ADD COLUMN didRenewalMode VARCHAR(20) NOT NULL DEFAULT \'per_did\'');

        // Company table - Add DID renewal anchor
        $this->addSql('ALTER TABLE Companies ADD COLUMN didRenewalAnchor DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign key first
        $this->addSql('ALTER TABLE DDIs DROP FOREIGN KEY FK_ddi_reserved_company');

        // Remove indexes
        $this->addSql('DROP INDEX idx_ddi_inventory_status ON DDIs');
        $this->addSql('DROP INDEX idx_ddi_next_renewal ON DDIs');
        $this->addSql('DROP INDEX idx_ddi_reserved_company ON DDIs');

        // Remove DDI columns
        $this->addSql('ALTER TABLE DDIs DROP COLUMN setupPrice');
        $this->addSql('ALTER TABLE DDIs DROP COLUMN monthlyPrice');
        $this->addSql('ALTER TABLE DDIs DROP COLUMN inventoryStatus');
        $this->addSql('ALTER TABLE DDIs DROP COLUMN assignedAt');
        $this->addSql('ALTER TABLE DDIs DROP COLUMN nextRenewalAt');
        $this->addSql('ALTER TABLE DDIs DROP COLUMN reservedForCompanyId');
        $this->addSql('ALTER TABLE DDIs DROP COLUMN reservedUntil');

        // Remove Brand column
        $this->addSql('ALTER TABLE Brands DROP COLUMN didRenewalMode');

        // Remove Company column
        $this->addSql('ALTER TABLE Companies DROP COLUMN didRenewalAnchor');
    }
}
