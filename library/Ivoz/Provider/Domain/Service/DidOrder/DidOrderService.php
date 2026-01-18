<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\DidOrder;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrder;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Psr\Log\LoggerInterface;

/**
 * DID Order Service - Handles order creation for postpaid customers
 *
 * Postpaid customers request DIDs which require admin approval.
 * The DID is reserved during the approval period (24 hours).
 *
 * Sends email notification when order is created.
 */
class DidOrderService implements DidOrderServiceInterface
{
    private const RESERVATION_HOURS = 24;

    public function __construct(
        private readonly EntityTools $entityTools,
        private readonly DdiRepository $ddiRepository,
        private readonly LoggerInterface $logger,
        private readonly ?DidOrderEmailSenderInterface $emailSender = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function canCreateOrders(CompanyInterface $company): bool
    {
        return $company->getBillingMethod() === 'postpaid';
    }

    /**
     * @inheritDoc
     */
    public function preview(CompanyInterface $company, DdiInterface $ddi): array
    {
        $canOrder = $this->canCreateOrders($company)
            && $this->isDdiAvailable($ddi);

        $country = $ddi->getCountry();
        $countryDisplay = 'Unknown';
        if ($country !== null) {
            $nameObj = $country->getName();
            if ($nameObj !== null) {
                $countryDisplay = $nameObj->getEn() ?? 'Unknown';
            }
        }

        return [
            'ddi' => $ddi->getDdie164(),
            'ddiId' => $ddi->getId(),
            'country' => $countryDisplay,
            'setupFee' => $ddi->getSetupPrice(),
            'monthlyFee' => $ddi->getMonthlyPrice(),
            'canOrder' => $canOrder,
            'reservationDuration' => sprintf('%d hours', self::RESERVATION_HOURS),
        ];
    }

    /**
     * @inheritDoc
     */
    public function createOrder(CompanyInterface $company, DdiInterface $ddi): DidOrderResult
    {
        $this->logger->info(sprintf(
            'DID order request: Company #%d requesting DID #%d (%s)',
            $company->getId(),
            $ddi->getId(),
            $ddi->getDdie164()
        ));

        // Step 1: Validate company is postpaid
        if (!$this->canCreateOrders($company)) {
            $this->logger->warning(sprintf(
                'DID order failed: Company #%d is not postpaid (billing method: %s)',
                $company->getId(),
                $company->getBillingMethod()
            ));
            return DidOrderResult::companyNotPostpaid();
        }

        // Step 2: Validate DID is available
        if (!$this->isDdiAvailable($ddi)) {
            $this->logger->warning(sprintf(
                'DID order failed: DID #%d is not available (status: %s)',
                $ddi->getId(),
                $ddi->getInventoryStatus() ?? 'null'
            ));
            return DidOrderResult::ddiNotAvailable($ddi->getId());
        }

        // Step 3: Reserve the DID
        $reservedUntil = new \DateTime(sprintf('+%d hours', self::RESERVATION_HOURS));
        $this->reserveDdi($ddi, $company, $reservedUntil);

        // Step 4: Create the order
        $order = $this->createDidOrder($company, $ddi);

        $this->logger->info(sprintf(
            'DID order created: Order #%d for Company #%d, DID #%d, reserved until %s',
            $order->getId(),
            $company->getId(),
            $ddi->getId(),
            $reservedUntil->format('Y-m-d H:i:s')
        ));

        // Send order created notification email
        if ($this->emailSender !== null) {
            try {
                $this->emailSender->sendOrderCreatedNotification($order);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'DID order: Failed to send notification email for Order #%d: %s',
                    $order->getId(),
                    $e->getMessage()
                ));
                // Don't fail the order creation if email fails
            }
        }

        return DidOrderResult::orderCreated($order);
    }

    /**
     * Check if DID is available for ordering
     */
    private function isDdiAvailable(DdiInterface $ddi): bool
    {
        // Refresh from database to get current state
        $currentDdi = $this->ddiRepository->find($ddi->getId());

        if (!$currentDdi) {
            return false;
        }

        // Must be in available status
        if ($currentDdi->getInventoryStatus() !== DdiInterface::INVENTORYSTATUS_AVAILABLE) {
            return false;
        }

        // Must not be assigned to any company
        if ($currentDdi->getCompany() !== null) {
            return false;
        }

        return true;
    }

    /**
     * Reserve a DID for the company
     */
    private function reserveDdi(
        DdiInterface $ddi,
        CompanyInterface $company,
        \DateTimeInterface $reservedUntil
    ): void {
        $ddiDto = $ddi->toDto();
        $ddiDto
            ->setInventoryStatus(DdiInterface::INVENTORYSTATUS_RESERVED)
            ->setReservedForCompanyId($company->getId())
            ->setReservedUntil($reservedUntil);

        $this->entityTools->persistDto($ddiDto, $ddi, true);
    }

    /**
     * Create a DidOrder entity
     */
    private function createDidOrder(
        CompanyInterface $company,
        DdiInterface $ddi
    ): DidOrderInterface {
        $orderDto = DidOrder::createDto();
        $orderDto
            ->setCompanyId($company->getId())
            ->setDdiId($ddi->getId())
            ->setStatus(DidOrderInterface::STATUS_PENDING_APPROVAL)
            ->setRequestedAt(new \DateTime())
            ->setSetupFee($ddi->getSetupPrice())
            ->setMonthlyFee($ddi->getMonthlyPrice());

        /** @var DidOrderInterface $order */
        $order = $this->entityTools->persistDto($orderDto, null, true);

        return $order;
    }
}
