<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Ivoz\Core\Infrastructure\Persistence\Doctrine\LoggableMigration;

/**
 * Add didOrder notification template type for DID order lifecycle emails
 */
final class Version20260118200000 extends LoggableMigration
{
    public function getDescription(): string
    {
        return 'Add didOrder notification template for DID order lifecycle emails (pending, approved, rejected, expired)';
    }

    public function up(Schema $schema): void
    {
        // Update the type enum comment to include 'didOrder'
        $this->addSql("ALTER TABLE NotificationTemplates CHANGE type type VARCHAR(25) NOT NULL COMMENT '[enum:voicemail|fax|limit|lowbalance|invoice|callCsv|maxDailyUsage|accessCredentials|suspension|didOrder]'");

        // Insert the generic DID order notification template
        $this->addSql("INSERT INTO NotificationTemplates (name, type) VALUES ('Generic DID Order Notification Template', 'didOrder')");

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
            'Solicitud de DID \${ORDER_STATUS} - \${DID_NUMBER}',
            'Estimado cliente,\n\nLe informamos sobre su solicitud de DID:\n\nNúmero DID: \${DID_NUMBER}\nEmpresa: \${COMPANY_NAME}\nEstado: \${ORDER_STATUS}\nFecha: \${ORDER_DATE}\n\nTarifa de activación: €\${SETUP_FEE}\nTarifa mensual: €\${MONTHLY_FEE}\n\nMotivo de rechazo: \${REJECTION_REASON}\n\nSi tiene alguna pregunta, por favor contacte con soporte.\n\nAtentamente,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='didOrder' AND brandId IS NULL),
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
            'DID Order \${ORDER_STATUS} - \${DID_NUMBER}',
            'Dear customer,\n\nWe would like to inform you about your DID order:\n\nDID Number: \${DID_NUMBER}\nCompany: \${COMPANY_NAME}\nStatus: \${ORDER_STATUS}\nDate: \${ORDER_DATE}\n\nSetup Fee: €\${SETUP_FEE}\nMonthly Fee: €\${MONTHLY_FEE}\n\nRejection Reason: \${REJECTION_REASON}\n\nIf you have any questions, please contact support.\n\nBest regards,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='didOrder' AND brandId IS NULL),
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
            'Sol·licitud de DID \${ORDER_STATUS} - \${DID_NUMBER}',
            'Benvolgut client,\n\nL''informem sobre la seva sol·licitud de DID:\n\nNúmero DID: \${DID_NUMBER}\nEmpresa: \${COMPANY_NAME}\nEstat: \${ORDER_STATUS}\nData: \${ORDER_DATE}\n\nTarifa d''activació: €\${SETUP_FEE}\nTarifa mensual: €\${MONTHLY_FEE}\n\nMotiu de rebuig: \${REJECTION_REASON}\n\nSi té alguna pregunta, si us plau contacti amb suport.\n\nAtentament,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='didOrder' AND brandId IS NULL),
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
            'Ordine DID \${ORDER_STATUS} - \${DID_NUMBER}',
            'Gentile cliente,\n\nLa informiamo riguardo al suo ordine DID:\n\nNumero DID: \${DID_NUMBER}\nAzienda: \${COMPANY_NAME}\nStato: \${ORDER_STATUS}\nData: \${ORDER_DATE}\n\nCosto di attivazione: €\${SETUP_FEE}\nCosto mensile: €\${MONTHLY_FEE}\n\nMotivo del rifiuto: \${REJECTION_REASON}\n\nPer qualsiasi domanda, contatti il supporto.\n\nCordiali saluti,\n\${BRAND_NAME}\n',
            (SELECT id FROM NotificationTemplates WHERE type='didOrder' AND brandId IS NULL),
            (SELECT id FROM Languages WHERE iden = 'it'),
            'text/plain'
        )");
    }

    public function down(Schema $schema): void
    {
        // Delete DID order notification template and contents (cascade delete will remove contents)
        $this->addSql("DELETE FROM NotificationTemplates WHERE type='didOrder'");

        // Restore previous enum comment (with suspension)
        $this->addSql("ALTER TABLE NotificationTemplates CHANGE type type VARCHAR(25) NOT NULL COMMENT '[enum:voicemail|fax|limit|lowbalance|invoice|callCsv|maxDailyUsage|accessCredentials|suspension]'");
    }
}
