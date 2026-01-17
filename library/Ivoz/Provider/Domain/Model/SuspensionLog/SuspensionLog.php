<?php

namespace Ivoz\Provider\Domain\Model\SuspensionLog;

/**
 * SuspensionLog
 *
 * Audit trail for company/DDI suspension events triggered by WHMCS webhooks
 */
class SuspensionLog extends SuspensionLogAbstract implements SuspensionLogInterface
{
    use SuspensionLogTrait;

    /**
     * @codeCoverageIgnore
     * @return array<string, mixed>
     */
    public function getChangeSet(): array
    {
        return parent::getChangeSet();
    }

    /**
     * Get id
     * @codeCoverageIgnore
     * @return integer
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    protected function sanitizeValues(): void
    {
        // If DDI is provided, ensure the DDI belongs to the same company
        if ($this->getDdi() !== null && $this->getDdi()->getCompany()->getId() !== $this->getCompany()->getId()) {
            throw new \DomainException('DDI must belong to the same company');
        }
    }

    /**
     * Check if this is a company-level suspension
     */
    public function isCompanySuspension(): bool
    {
        return in_array($this->getAction(), [self::ACTION_SUSPEND, self::ACTION_UNSUSPEND], true);
    }

    /**
     * Check if this is a DDI-level suspension
     */
    public function isDdiSuspension(): bool
    {
        return in_array($this->getAction(), [self::ACTION_SUSPEND_DDI, self::ACTION_UNSUSPEND_DDI], true);
    }

    /**
     * Check if this is a suspension (not unsuspension) action
     */
    public function isSuspension(): bool
    {
        return in_array($this->getAction(), [self::ACTION_SUSPEND, self::ACTION_SUSPEND_DDI], true);
    }
}
