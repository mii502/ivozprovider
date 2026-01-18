<?php

declare(strict_types=1);

/**
 * DID Reservation Cleanup Command - Cron job for cleaning expired DID reservations
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Application/Command/DidReservationCleanupCommand.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-order
 *
 * Usage: php bin/console ivozprovider:did:cleanup-reservations
 * Cron:  0 3 * * * php /opt/irontec/ivozprovider/web/rest/brand/bin/console ivozprovider:did:cleanup-reservations
 */

namespace Ivoz\Provider\Application\Command;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderRepository;
use Ivoz\Provider\Domain\Service\DidOrder\DidOrderEmailSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ivozprovider:did:cleanup-reservations',
    description: 'Clean up expired DID reservations and cancel associated orders'
)]
class DidReservationCleanupCommand extends Command
{
    public function __construct(
        private readonly DidOrderRepository $didOrderRepository,
        private readonly EntityTools $entityTools,
        private readonly LoggerInterface $logger,
        private readonly ?DidOrderEmailSenderInterface $emailSender = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be done without making changes'
            )
            ->addOption(
                'no-email',
                null,
                InputOption::VALUE_NONE,
                'Skip sending notification emails'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command cleans up expired DID reservations.

<info>What it does:</info>
  1. Finds pending orders where the DID reservation has expired
  2. Updates order status to 'expired'
  3. Releases the DID reservation (back to available)
  4. Sends notification email to the customer

<info>Examples:</info>
  Preview what would be cleaned (dry-run):
    <info>php %command.full_name% --dry-run</info>

  Run cleanup without notifications:
    <info>php %command.full_name% --no-email</info>

  Full cleanup with notifications:
    <info>php %command.full_name%</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $skipEmail = (bool) $input->getOption('no-email');

        if ($dryRun) {
            $io->warning('DRY-RUN MODE: No changes will be made');
        }

        $io->title(sprintf(
            'DID Reservation Cleanup%s',
            $dryRun ? ' (DRY-RUN)' : ''
        ));

        // Find expired orders
        $expiredOrders = $this->didOrderRepository->findExpiredOrders();

        if (empty($expiredOrders)) {
            $io->success('No expired reservations found');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d expired reservation(s)', count($expiredOrders)));
        $io->newLine();

        // Statistics
        $stats = [
            'orders_expired' => 0,
            'ddis_released' => 0,
            'emails_sent' => 0,
            'errors' => 0,
        ];

        foreach ($expiredOrders as $order) {
            $this->processExpiredOrder($io, $order, $dryRun, $skipEmail, $stats);
        }

        // Print summary
        $io->newLine();
        $io->section('Summary');
        $io->definitionList(
            ['Orders expired' => (string) $stats['orders_expired']],
            ['DDIs released' => (string) $stats['ddis_released']],
            ['Emails sent' => (string) $stats['emails_sent']],
            ['Errors' => (string) $stats['errors']]
        );

        if ($stats['errors'] > 0) {
            $io->warning('Completed with errors. Check logs for details.');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->info('DRY-RUN complete. Run without --dry-run to apply changes.');
        } else {
            $io->success('DID reservation cleanup complete');
        }

        return Command::SUCCESS;
    }

    /**
     * Process a single expired order
     */
    private function processExpiredOrder(
        SymfonyStyle $io,
        DidOrderInterface $order,
        bool $dryRun,
        bool $skipEmail,
        array &$stats
    ): void {
        $ddi = $order->getDdi();
        $company = $order->getCompany();

        $io->text(sprintf(
            '  • Order #%d: %s (Company: %s)',
            $order->getId(),
            $ddi->getDdie164(),
            $company->getName()
        ));

        if ($dryRun) {
            $io->text('    → Would expire order and release DID');
            $stats['orders_expired']++;
            $stats['ddis_released']++;
            if (!$skipEmail && $this->emailSender !== null) {
                $stats['emails_sent']++;
            }
            return;
        }

        try {
            // Step 1: Update order status to expired
            $orderDto = $order->toDto();
            $orderDto->setStatus(DidOrderInterface::STATUS_EXPIRED);
            $this->entityTools->persistDto($orderDto, $order, true);
            $stats['orders_expired']++;

            $this->logger->info(sprintf(
                'DID order expired: Order #%d, DID %s, Company %s',
                $order->getId(),
                $ddi->getDdie164(),
                $company->getName()
            ));

            // Step 2: Release the DID reservation
            $ddiDto = $ddi->toDto();
            $ddiDto
                ->setInventoryStatus(DdiInterface::INVENTORYSTATUS_AVAILABLE)
                ->setReservedForCompanyId(null)
                ->setReservedUntil(null);
            $this->entityTools->persistDto($ddiDto, $ddi, true);
            $stats['ddis_released']++;

            $this->logger->info(sprintf(
                'DID released: %s (ID: %d) - reservation expired',
                $ddi->getDdie164(),
                $ddi->getId()
            ));

            // Step 3: Send notification email
            if (!$skipEmail && $this->emailSender !== null) {
                try {
                    $this->emailSender->sendOrderExpiredNotification($order);
                    $stats['emails_sent']++;
                    $io->text('    → Order expired, DID released, email sent');
                } catch (\Exception $e) {
                    $this->logger->warning(sprintf(
                        'Failed to send expiration email for order #%d: %s',
                        $order->getId(),
                        $e->getMessage()
                    ));
                    $io->text('    → Order expired, DID released (email failed)');
                }
            } else {
                $io->text('    → Order expired, DID released');
            }
        } catch (\Exception $e) {
            $stats['errors']++;
            $io->error(sprintf('    ERROR: %s', $e->getMessage()));
            $this->logger->error(sprintf(
                'Failed to expire order #%d: %s',
                $order->getId(),
                $e->getMessage()
            ));
        }
    }
}
