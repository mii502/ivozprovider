<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Add suspension notification template type for WHMCS integration
 */
final class Version20260117000003 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add suspension notification template for company suspension/unsuspension emails';
    }

    public function up(Schema $schema): void
    {
        // Update the type enum comment to include 'suspension'
        $this->addSql("ALTER TABLE NotificationTemplates CHANGE type type VARCHAR(25) NOT NULL COMMENT '[enum:voicemail|fax|limit|lowbalance|invoice|callCsv|maxDailyUsage|accessCredentials|suspension]'");

        // Insert the generic suspension notification template
        $this->addSql("INSERT INTO NotificationTemplates (name, type) VALUES ('Generic Suspension Notification Template', 'suspension')");

        // Spanish content
        $this->addSql("INSERT INTO NotificationTemplatesContents (
            fromName,
            fromAddress,
            subject,
            body,
            notificationTemplateId,
            languageId,
            bodyType
        ) VALUES (
            'Notificaciones IvozProvider',
            'no-reply@ivozprovider.com',
            'Notificación de estado de cuenta - \${COMPANY_NAME}',
            'Estimado cliente,\n\nLe informamos que su cuenta \${COMPANY_NAME} ha sido \${SUSPENSION_STATUS}.\n\nFecha: \${SUSPENSION_DATE}\n\nSi tiene alguna pregunta, por favor contacte con soporte.\n\nAtentamente,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='suspension' AND brandId IS NULL),
            (SELECT id FROM Languages WHERE iden = 'es'),
            'text/plain'
        )");

        // English content
        $this->addSql("INSERT INTO NotificationTemplatesContents (
            fromName,
            fromAddress,
            subject,
            body,
            notificationTemplateId,
            languageId,
            bodyType
        ) VALUES (
            'IvozProvider Notifications',
            'no-reply@ivozprovider.com',
            'Account Status Notification - \${COMPANY_NAME}',
            'Dear customer,\n\nWe would like to inform you that your account \${COMPANY_NAME} has been \${SUSPENSION_STATUS}.\n\nDate: \${SUSPENSION_DATE}\n\nIf you have any questions, please contact support.\n\nBest regards,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='suspension' AND brandId IS NULL),
            (SELECT id FROM Languages WHERE iden = 'en'),
            'text/plain'
        )");

        // Catalan content
        $this->addSql("INSERT INTO NotificationTemplatesContents (
            fromName,
            fromAddress,
            subject,
            body,
            notificationTemplateId,
            languageId,
            bodyType
        ) VALUES (
            'Notificacions IvozProvider',
            'no-reply@ivozprovider.com',
            'Notificació d''estat del compte - \${COMPANY_NAME}',
            'Benvolgut client,\n\nL''informem que el seu compte \${COMPANY_NAME} ha estat \${SUSPENSION_STATUS}.\n\nData: \${SUSPENSION_DATE}\n\nSi té alguna pregunta, si us plau contacti amb suport.\n\nAtentament,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='suspension' AND brandId IS NULL),
            (SELECT id FROM Languages WHERE iden = 'ca'),
            'text/plain'
        )");

        // Italian content
        $this->addSql("INSERT INTO NotificationTemplatesContents (
            fromName,
            fromAddress,
            subject,
            body,
            notificationTemplateId,
            languageId,
            bodyType
        ) VALUES (
            'Notifiche IvozProvider',
            'no-reply@ivozprovider.com',
            'Notifica stato account - \${COMPANY_NAME}',
            'Gentile cliente,\n\nLa informiamo che il suo account \${COMPANY_NAME} è stato \${SUSPENSION_STATUS}.\n\nData: \${SUSPENSION_DATE}\n\nPer qualsiasi domanda, contatti il supporto.\n\nCordiali saluti,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='suspension' AND brandId IS NULL),
            (SELECT id FROM Languages WHERE iden = 'it'),
            'text/plain'
        )");
    }

    public function down(Schema $schema): void
    {
        // Delete suspension notification template and contents (cascade delete will remove contents)
        $this->addSql("DELETE FROM NotificationTemplates WHERE type='suspension'");

        // Restore original enum comment
        $this->addSql("ALTER TABLE NotificationTemplates CHANGE type type VARCHAR(25) NOT NULL COMMENT '[enum:voicemail|fax|limit|lowbalance|invoice|callCsv|maxDailyUsage|accessCredentials]'");
    }
}
