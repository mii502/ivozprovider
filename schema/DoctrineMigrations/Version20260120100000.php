<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Add ddiE164 field to Invoices for historical DDI tracking
 *
 * This field stores the E.164 phone number permanently, allowing the
 * UnlinkDdi pattern (delete and recreate DDI) without losing invoice history.
 */
final class Version20260120100000 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add ddiE164 field to Invoices for historical DDI tracking when UnlinkDdi is used';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add the new column
        $this->addSql('ALTER TABLE Invoices ADD COLUMN ddiE164 VARCHAR(25) DEFAULT NULL');

        // Step 2: Backfill from existing DDI FK relationships
        $this->addSql('
            UPDATE Invoices i
            INNER JOIN DDIs d ON i.ddiId = d.id
            SET i.ddiE164 = d.DdiE164
            WHERE i.ddiId IS NOT NULL AND i.ddiE164 IS NULL
        ');

        // Step 3: Add index for historical queries by phone number
        $this->addSql('CREATE INDEX idx_invoice_ddiE164 ON Invoices (ddiE164)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_invoice_ddiE164 ON Invoices');
        $this->addSql('ALTER TABLE Invoices DROP COLUMN ddiE164');
    }
}
