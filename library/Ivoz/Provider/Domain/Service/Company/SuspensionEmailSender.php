<?php

namespace Ivoz\Provider\Domain\Service\Company;

use Ivoz\Core\Domain\Model\Mailer\Message;
use Ivoz\Core\Domain\Service\MailerClientInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorRepository;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\NotificationTemplate\NotificationTemplateRepository;
use Ivoz\Provider\Domain\Model\NotificationTemplateContent\NotificationTemplateContentInterface;
use Psr\Log\LoggerInterface;

/**
 * Send email notifications when a company is suspended or unsuspended
 */
class SuspensionEmailSender implements CompanyLifecycleEventHandlerInterface
{
    public const ON_COMMIT_PRIORITY = CompanyLifecycleEventHandlerInterface::PRIORITY_LOW;

    public function __construct(
        private MailerClientInterface $mailer,
        private NotificationTemplateRepository $notificationTemplateRepository,
        private AdministratorRepository $administratorRepository,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            self::EVENT_ON_COMMIT => self::ON_COMMIT_PRIORITY
        ];
    }

    public function execute(CompanyInterface $company): void
    {
        // Only send email if enabled status has changed
        if (!$company->hasChanged('enabled')) {
            return;
        }

        $targetEmail = $this->getTargetEmail($company);
        if (!$targetEmail) {
            $this->logger->info(
                sprintf(
                    'No email address found for company %s (ID: %d) - skipping suspension email',
                    $company->getName(),
                    $company->getId()
                )
            );
            return;
        }

        $notificationTemplateContent = $this->getNotificationTemplateContent($company);
        if (!$notificationTemplateContent) {
            $this->logger->info(
                sprintf(
                    'No suspension notification template found for company %s (ID: %d) - skipping email',
                    $company->getName(),
                    $company->getId()
                )
            );
            return;
        }

        // Get data from template
        $fromName = $notificationTemplateContent->getFromName();
        $fromAddress = $notificationTemplateContent->getFromAddress();
        $bodyType = $notificationTemplateContent->getBodyType();
        $body = $this->parseVariables(
            $company,
            $notificationTemplateContent->getBody()
        );
        $subject = $this->parseVariables(
            $company,
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

        $this->logger->info(
            sprintf(
                'Suspension notification email sent to %s for company %s (ID: %d)',
                $targetEmail,
                $company->getName(),
                $company->getId()
            )
        );
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
        $notificationTemplate = $this->notificationTemplateRepository
            ->findSuspensionNotificationTemplateByCompany($company);

        if (!$notificationTemplate) {
            return null;
        }

        return $notificationTemplate->getContentsByLanguage(
            $company->getLanguage()
        );
    }

    /**
     * Parse template variables with company data
     */
    private function parseVariables(CompanyInterface $company, string $content): string
    {
        $enabled = $company->getEnabled();
        $statusText = $enabled ? 'activated' : 'suspended';

        $timezone = $company->getDefaultTimezone();
        $dateTimeZone = $timezone ? new \DateTimeZone($timezone->getTz()) : new \DateTimeZone('UTC');
        $now = new \DateTime('now', $dateTimeZone);

        $substitution = [
            '${COMPANY_NAME}' => $company->getName(),
            '${SUSPENSION_STATUS}' => $statusText,
            '${SUSPENSION_DATE}' => $now->format('Y-m-d H:i:s'),
            '${BRAND_NAME}' => $company->getBrand()->getName(),
        ];

        return str_replace(
            array_keys($substitution),
            array_values($substitution),
            $content
        );
    }
}
