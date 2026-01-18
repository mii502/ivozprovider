<?php

namespace Ivoz\Provider\Domain\Model\DidOrder;

use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;

/**
 * DidOrder
 */
class DidOrder extends DidOrderAbstract implements DidOrderInterface
{
    use DidOrderTrait;

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
        // Ensure status transitions are valid
        if ($this->hasChanged('status')) {
            $oldStatus = $this->getInitialValue('status');
            $newStatus = $this->getStatus();

            // Once approved, rejected, or expired, status cannot change
            $finalStatuses = [
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
                self::STATUS_EXPIRED,
            ];

            if (in_array($oldStatus, $finalStatuses, true) && $oldStatus !== $newStatus) {
                throw new \DomainException(
                    sprintf('Cannot change status from %s to %s', $oldStatus, $newStatus),
                    400
                );
            }
        }

        // Set approvedAt when status changes to approved
        if ($this->hasChanged('status') && $this->getStatus() === self::STATUS_APPROVED) {
            if ($this->getApprovedAt() === null) {
                $this->setApprovedAt(new \DateTime());
            }
        }

        // Set rejectedAt when status changes to rejected
        if ($this->hasChanged('status') && $this->getStatus() === self::STATUS_REJECTED) {
            if ($this->getRejectedAt() === null) {
                $this->setRejectedAt(new \DateTime());
            }
        }
    }

    /**
     * Check if order is pending approval
     */
    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if order has been approved
     */
    public function isApproved(): bool
    {
        return $this->getStatus() === self::STATUS_APPROVED;
    }

    /**
     * Check if order has been rejected
     */
    public function isRejected(): bool
    {
        return $this->getStatus() === self::STATUS_REJECTED;
    }

    /**
     * Check if order has expired
     */
    public function isExpired(): bool
    {
        return $this->getStatus() === self::STATUS_EXPIRED;
    }

    /**
     * Approve the order
     */
    public function approve(AdministratorInterface $admin): static
    {
        if (!$this->isPending()) {
            throw new \DomainException(
                'Only pending orders can be approved',
                400
            );
        }

        $this->setStatus(self::STATUS_APPROVED);
        $this->setApprovedBy($admin);
        $this->setApprovedAt(new \DateTime());

        return $this;
    }

    /**
     * Reject the order with a reason
     */
    public function reject(string $reason): static
    {
        if (!$this->isPending()) {
            throw new \DomainException(
                'Only pending orders can be rejected',
                400
            );
        }

        $this->setStatus(self::STATUS_REJECTED);
        $this->setRejectedAt(new \DateTime());
        $this->setRejectionReason($reason);

        return $this;
    }

    /**
     * Mark order as expired (called by cleanup cron)
     */
    public function markExpired(): static
    {
        if (!$this->isPending()) {
            throw new \DomainException(
                'Only pending orders can expire',
                400
            );
        }

        $this->setStatus(self::STATUS_EXPIRED);

        return $this;
    }
}
