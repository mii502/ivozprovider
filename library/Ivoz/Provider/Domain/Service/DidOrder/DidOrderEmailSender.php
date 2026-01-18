<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\DidOrder;

use Ivoz\Core\Domain\Model\Mailer\Message;
use Ivoz\Core\Domain\Service\MailerClientInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorRepository;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Ivoz\Provider\Domain\Model\NotificationTemplate\NotificationTemplateInterface;
use Ivoz\Provider\Domain\Model\NotificationTemplate\NotificationTemplateRepository;
use Ivoz\Provider\Domain\Model\NotificationTemplateContent\NotificationTemplateContentInterface;
use Psr\Log\LoggerInterface;

/**
 * Send email notifications for DID order lifecycle events
 *
 * Template type: 'didOrder'
 * Variables: ${DID_NUMBER}, ${COMPANY_NAME}, ${ORDER_STATUS}, ${ORDER_DATE},
 *            ${SETUP_FEE}, ${MONTHLY_FEE}, ${REJECTION_REASON}, ${BRAND_NAME}
 */
class DidOrderEmailSender implements DidOrderEmailSenderInterface
{
    public const TEMPLATE_TYPE = 'didOrder';

    public function __construct(
        private readonly MailerClientInterface $mailer,
        private readonly NotificationTemplateRepository $notificationTemplateRepository,
        private readonly AdministratorRepository $administratorRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendOrderCreatedNotification(DidOrderInterface $order): void
    {
        $this->sendNotification($order, 'pending_approval');
    }

    /**
     * @inheritDoc
     */
    public function sendOrderApprovedNotification(DidOrderInterface $order): void
    {
        $this->sendNotification($order, 'approved');
    }

    /**
     * @inheritDoc
     */
    public function sendOrderRejectedNotification(DidOrderInterface $order): void
    {
        $this->sendNotification($order, 'rejected');
    }

    /**
     * @inheritDoc
     */
    public function sendOrderExpiredNotification(DidOrderInterface $order): void
    {
        $this->sendNotification($order, 'expired');
    }

    /**
     * Send notification for a specific order status
     */
    private function sendNotification(DidOrderInterface $order, string $statusType): void
    {
        $company = $order->getCompany();

        $targetEmail = $this->getTargetEmail($company);
        if (!$targetEmail) {
            $this->logger->info(sprintf(
                'No email address found for company %s (ID: %d) - skipping DID order %s email',
                $company->getName(),
                $company->getId(),
                $statusType
            ));
            return;
        }

        $notificationTemplateContent = $this->getNotificationTemplateContent($company);
        if (!$notificationTemplateContent) {
            $this->logger->info(sprintf(
                'No DID order notification template found for company %s (ID: %d) - skipping %s email',
                $company->getName(),
                $company->getId(),
                $statusType
            ));
            return;
        }

        // Get data from template
        $fromName = $notificationTemplateContent->getFromName();
        $fromAddress = $notificationTemplateContent->getFromAddress();
        $bodyType = $notificationTemplateContent->getBodyType();
        $body = $this->parseVariables(
            $order,
            $statusType,
            $notificationTemplateContent->getBody()
        );
        $subject = $this->parseVariables(
            $order,
            $statusType,
            $notificationTemplateContent->getSubject()
        );

        // Create and send the email
        $message = new Message();
        $message->setBody($body, $bodyType)
            ->setSubject($subject)
            ->setFromAddress((string) $fromAddress)
            ->setFromName((string) $fromName)
            ->setToAddress($targetEmail);

        $this->mailer->send($message);

        $this->logger->info(sprintf(
            'DID order %s notification sent to %s for order #%d (Company: %s)',
            $statusType,
            $targetEmail,
            $order->getId(),
            $company->getName()
        ));
    }

    /**
     * Get the email address to send the notification to
     */
    private function getTargetEmail(CompanyInterface $company): ?string
    {
        // Try to find an active administrator for this company
        $admin = $this->administratorRepository->findFirstActiveByCompany($company);
        if ($admin) {
            return $admin->getEmail();
        }

        // Fallback to maxDailyUsageEmail if set (it's a company contact email)
        $maxDailyUsageEmail = $company->getMaxDailyUsageEmail();
        if (!empty($maxDailyUsageEmail)) {
            return $maxDailyUsageEmail;
        }

        return null;
    }

    /**
     * Get notification template content for company language
     */
    private function getNotificationTemplateContent(CompanyInterface $company): ?NotificationTemplateContentInterface
    {
        $notificationTemplate = $this->findDidOrderTemplateByCompany($company);

        if (!$notificationTemplate) {
            return null;
        }

        return $notificationTemplate->getContentsByLanguage(
            $company->getLanguage()
        );
    }

    /**
     * Find DID order notification template for a company
     */
    private function findDidOrderTemplateByCompany(CompanyInterface $company): ?NotificationTemplateInterface
    {
        $language = $company->getLanguage();

        // First try to find a brand-specific template
        $brandTemplate = $this->notificationTemplateRepository->findOneBy([
            'brand' => $company->getBrand(),
            'type' => self::TEMPLATE_TYPE
        ]);

        if (
            $brandTemplate
            && $brandTemplate->getContentsByLanguage($language)
        ) {
            return $brandTemplate;
        }

        // Fall back to generic template
        /** @var NotificationTemplateInterface|null $genericTemplate */
        $genericTemplate = $this->notificationTemplateRepository->findOneBy([
            'brand' => null,
            'type' => self::TEMPLATE_TYPE
        ]);

        return $genericTemplate;
    }

    /**
     * Parse template variables with order data
     */
    private function parseVariables(DidOrderInterface $order, string $statusType, string $content): string
    {
        $company = $order->getCompany();
        $ddi = $order->getDdi();

        // Get timezone for date formatting
        $timezone = $company->getDefaultTimezone();
        $dateTimeZone = $timezone ? new \DateTimeZone($timezone->getTz()) : new \DateTimeZone('UTC');

        // Format date based on status type
        $statusDate = match ($statusType) {
            'pending_approval' => $order->getRequestedAt(),
            'approved' => $order->getApprovedAt() ?? new \DateTime(),
            'rejected' => $order->getRejectedAt() ?? new \DateTime(),
            'expired' => new \DateTime(),
            default => new \DateTime()
        };
        $formattedDate = $statusDate->setTimezone($dateTimeZone)->format('Y-m-d H:i:s');

        // Translate status for display
        $statusText = match ($statusType) {
            'pending_approval' => 'submitted for approval',
            'approved' => 'approved and activated',
            'rejected' => 'rejected',
            'expired' => 'expired',
            default => $statusType
        };

        // Get rejection reason if applicable
        $rejectionReason = $statusType === 'rejected'
            ? ($order->getRejectionReason() ?? 'No reason provided')
            : 'N/A';

        $substitution = [
            '${DID_NUMBER}' => $ddi->getDdie164(),
            '${COMPANY_NAME}' => $company->getName(),
            '${ORDER_STATUS}' => $statusText,
            '${ORDER_DATE}' => $formattedDate,
            '${SETUP_FEE}' => sprintf('%.2f', $order->getSetupFee()),
            '${MONTHLY_FEE}' => sprintf('%.2f', $order->getMonthlyFee()),
            '${REJECTION_REASON}' => $rejectionReason,
            '${BRAND_NAME}' => $company->getBrand()->getName(),
        ];

        return str_replace(
            array_keys($substitution),
            array_values($substitution),
            $content
        );
    }
}
