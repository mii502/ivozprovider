<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\DidOrder;

use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;

/**
 * Interface for DID Order email notifications
 */
interface DidOrderEmailSenderInterface
{
    /**
     * Send notification when order is created (pending approval)
     *
     * Notifies the customer that their order has been received.
     */
    public function sendOrderCreatedNotification(DidOrderInterface $order): void;

    /**
     * Send notification when order is approved
     *
     * Notifies the customer that their DID is now active.
     */
    public function sendOrderApprovedNotification(DidOrderInterface $order): void;

    /**
     * Send notification when order is rejected
     *
     * Notifies the customer with the rejection reason.
     */
    public function sendOrderRejectedNotification(DidOrderInterface $order): void;

    /**
     * Send notification when order has expired
     *
     * Notifies the customer that the reservation expired.
     */
    public function sendOrderExpiredNotification(DidOrderInterface $order): void;
}
