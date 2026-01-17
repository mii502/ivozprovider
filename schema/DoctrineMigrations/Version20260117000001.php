<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Add enabled column to Companies table for WHMCS suspension integration
 */
final class Version20260117000001 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add enabled column to Companies table for suspension support';
    }

    public function up(Schema $schema): void
    {
        // Add the enabled column with default true (1)
        $this->addSql('ALTER TABLE Companies ADD enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT \'Suspension status: false blocks all calls\'');

        // Ensure all existing companies are enabled by default
        $this->addSql('UPDATE Companies SET enabled = 1 WHERE enabled IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Companies DROP COLUMN enabled');
    }
}
