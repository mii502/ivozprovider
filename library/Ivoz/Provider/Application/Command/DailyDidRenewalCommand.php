<?php

declare(strict_types=1);

/**
 * Daily DID Renewal Command - Cron job for processing DID renewals
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Application/Command/DailyDidRenewalCommand.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-renewal
 *
 * Supports two renewal modes (per Brand.didRenewalMode):
 * - per_did (default): Each DID has independent renewal dates
 * - consolidated: All DIDs renew on company anchor date
 *
 * Usage: php bin/console ivozprovider:did:renew
 * Cron:  0 2 * * * php /opt/irontec/ivozprovider/web/rest/brand/bin/console ivozprovider:did:renew
 */

namespace Ivoz\Provider\Application\Command;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyRepository;
use Ivoz\Provider\Domain\Service\Ddi\DidRenewalServiceInterface;
use Ivoz\Provider\Domain\Service\Ddi\FirstPeriodCalculator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ivozprovider:did:renew',
    description: 'Process daily DID renewals for prepaid companies'
)]
class DailyDidRenewalCommand extends Command
{
    public function __construct(
        private readonly DidRenewalServiceInterface $didRenewalService,
        private readonly CompanyRepository $companyRepository,
        private readonly EntityTools $entityTools,
        private readonly LoggerInterface $logger
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
                'company-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Process only a specific company ID'
            )
            ->addOption(
                'date',
                'd',
                InputOption::VALUE_REQUIRED,
                'Override renewal date (format: Y-m-d, default: today)'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command processes DID renewals for prepaid companies.

<info>Strategy:</info>
  1. Companies with sufficient balance → silent renewal with balance deduction
  2. Companies with insufficient balance → create WHMCS invoice for payment

<info>Examples:</info>
  List DIDs due for renewal (dry-run):
    <info>php %command.full_name% --dry-run</info>

  Process single company:
    <info>php %command.full_name% --company-id=123</info>

  Process for specific date:
    <info>php %command.full_name% --date=2026-01-20</info>

  Combine options:
    <info>php %command.full_name% --dry-run --company-id=123 --date=2026-01-20</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $companyIdFilter = $input->getOption('company-id');
        $dateOption = $input->getOption('date');

        // Parse date
        $date = new \DateTime('today');
        if ($dateOption !== null) {
            try {
                $date = new \DateTime($dateOption);
            } catch (\Exception $e) {
                $io->error(sprintf('Invalid date format: %s (expected Y-m-d)', $dateOption));
                return Command::FAILURE;
            }
        }

        if ($dryRun) {
            $io->warning('DRY-RUN MODE: No changes will be made');
        }

        $io->title(sprintf(
            'Processing DID renewals for %s%s',
            $date->format('Y-m-d'),
            $dryRun ? ' (DRY-RUN)' : ''
        ));

        // Get DIDs due for renewal grouped by company
        $ddisGroupedByCompany = $this->didRenewalService->getDdisForRenewalGroupedByCompany($date);

        // Filter by company ID if specified
        if ($companyIdFilter !== null) {
            $companyId = (int) $companyIdFilter;
            if (!isset($ddisGroupedByCompany[$companyId])) {
                $io->note(sprintf('No DIDs due for renewal for company ID %d', $companyId));
                return Command::SUCCESS;
            }
            $ddisGroupedByCompany = [$companyId => $ddisGroupedByCompany[$companyId]];
        }

        if (empty($ddisGroupedByCompany)) {
            $io->success('No DIDs due for renewal');
            return Command::SUCCESS;
        }

        // Statistics
        $stats = [
            'companies_processed' => 0,
            'ddis_renewed_balance' => 0,
            'ddis_sent_whmcs' => 0,
            'total_balance_deducted' => 0.0,
            'total_whmcs_invoiced' => 0.0,
            'errors' => 0,
        ];

        foreach ($ddisGroupedByCompany as $companyId => $ddis) {
            $company = $this->companyRepository->find($companyId);
            if ($company === null) {
                $io->warning(sprintf('Company #%d not found, skipping', $companyId));
                continue;
            }

            $this->processCompany($io, $company, $ddis, $dryRun, $stats);
        }

        // Print summary
        $io->newLine();
        $io->section('Summary');
        $io->definitionList(
            ['Companies processed' => (string) $stats['companies_processed']],
            ['DIDs renewed from balance' => (string) $stats['ddis_renewed_balance']],
            ['DIDs sent to WHMCS' => (string) $stats['ddis_sent_whmcs']],
            ['Total balance deducted' => sprintf('€%.2f', $stats['total_balance_deducted'])],
            ['Total WHMCS invoiced' => sprintf('€%.2f', $stats['total_whmcs_invoiced'])],
            ['Errors' => (string) $stats['errors']]
        );

        if ($stats['errors'] > 0) {
            $io->warning('Completed with errors. Check logs for details.');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->info('DRY-RUN complete. Run without --dry-run to apply changes.');
        } else {
            $io->success('DID renewal processing complete');
        }

        return Command::SUCCESS;
    }

    /**
     * Process DID renewals for a single company
     *
     * @param SymfonyStyle $io
     * @param CompanyInterface $company
     * @param array $ddis
     * @param bool $dryRun
     * @param array $stats
     */
    private function processCompany(
        SymfonyStyle $io,
        CompanyInterface $company,
        array $ddis,
        bool $dryRun,
        array &$stats
    ): void {
        $ddiCount = count($ddis);
        $totalCost = $this->didRenewalService->calculateRenewalCost($ddis);
        $balance = $this->didRenewalService->getCompanyBalance($company);
        $canRenewFromBalance = $this->didRenewalService->canRenewFromBalance($company, $ddis);

        // Get renewal mode from brand
        $brand = $company->getBrand();
        $renewalMode = $brand?->getDidRenewalMode() ?? FirstPeriodCalculator::MODE_PER_DID;
        $modeLabel = $renewalMode === FirstPeriodCalculator::MODE_PER_DID ? 'per_did' : 'consolidated';
        $anchor = $company->getDidRenewalAnchor();

        // Build mode info string
        $modeInfo = sprintf('[%s mode]', $modeLabel);
        if ($renewalMode === FirstPeriodCalculator::MODE_CONSOLIDATED && $anchor !== null) {
            $modeInfo .= sprintf(' anchor: %s', $anchor->format('Y-m-d'));
        }

        $io->section(sprintf('Company: %s (ID: %d) %s', $company->getName(), $company->getId(), $modeInfo));
        $io->text([
            sprintf('  - DIDs due: %d (total: €%.2f)', $ddiCount, $totalCost),
            sprintf('  - Balance: €%.2f', $balance),
            sprintf('  - Can renew from balance: %s', $canRenewFromBalance ? 'Yes' : 'No'),
        ]);

        // List DDIs
        if ($io->isVerbose()) {
            $ddiList = [];
            foreach ($ddis as $ddi) {
                $currentRenewal = $ddi->getNextRenewalAt();
                $currentRenewalStr = $currentRenewal?->format('Y-m-d') ?? 'not set';

                // Calculate expected new renewal date based on mode
                if ($renewalMode === FirstPeriodCalculator::MODE_PER_DID) {
                    // per_did: each DID advances by 1 month independently
                    $expectedNext = $currentRenewal !== null
                        ? (clone $currentRenewal)->modify('+1 month')->format('Y-m-d')
                        : 'N/A';
                } else {
                    // consolidated: all DIDs advance to anchor + 1 month
                    if ($anchor !== null) {
                        $expectedNext = (clone $anchor)->modify('+1 month')->format('Y-m-d');
                    } else {
                        $expectedNext = 'anchor not set';
                    }
                }

                $ddiList[] = sprintf(
                    '    • %s (€%.2f/mo, current: %s → new: %s)',
                    $ddi->getDdie164(),
                    $ddi->getMonthlyPrice() ?? 0,
                    $currentRenewalStr,
                    $expectedNext
                );
            }
            $io->text($ddiList);
        }

        // Process renewal
        if ($canRenewFromBalance) {
            $action = 'Silent renewal from balance';
            if (!$dryRun) {
                try {
                    $invoice = $this->didRenewalService->renewFromBalance($company, $ddis);
                    $io->text([
                        sprintf('  - Action: %s', $action),
                        sprintf('  - Invoice: #%s', $invoice->getNumber()),
                    ]);
                    $stats['ddis_renewed_balance'] += $ddiCount;
                    $stats['total_balance_deducted'] += $totalCost;

                    $this->logger->info(sprintf(
                        'DID renewal from balance: Company #%d, %d DDIs, €%.2f',
                        $company->getId(),
                        $ddiCount,
                        $totalCost
                    ));
                } catch (\Exception $e) {
                    $io->error(sprintf('  - ERROR: %s', $e->getMessage()));
                    $stats['errors']++;
                    $this->logger->error(sprintf(
                        'DID renewal failed for Company #%d: %s',
                        $company->getId(),
                        $e->getMessage()
                    ));
                    return;
                }
            } else {
                $io->text([
                    sprintf('  - Action: %s (DRY-RUN)', $action),
                    '  - Invoice: [would be created]',
                ]);
                $stats['ddis_renewed_balance'] += $ddiCount;
                $stats['total_balance_deducted'] += $totalCost;
            }
        } else {
            $action = 'Create WHMCS invoice';
            if (!$dryRun) {
                try {
                    $invoice = $this->didRenewalService->createWhmcsRenewalInvoice($company, $ddis);
                    $io->text([
                        sprintf('  - Action: %s', $action),
                        sprintf('  - Invoice: #%s (pending WHMCS sync)', $invoice->getNumber()),
                    ]);
                    $stats['ddis_sent_whmcs'] += $ddiCount;
                    $stats['total_whmcs_invoiced'] += $totalCost;

                    $this->logger->info(sprintf(
                        'DID renewal WHMCS invoice: Company #%d, %d DDIs, €%.2f',
                        $company->getId(),
                        $ddiCount,
                        $totalCost
                    ));
                } catch (\Exception $e) {
                    $io->error(sprintf('  - ERROR: %s', $e->getMessage()));
                    $stats['errors']++;
                    $this->logger->error(sprintf(
                        'DID renewal WHMCS invoice failed for Company #%d: %s',
                        $company->getId(),
                        $e->getMessage()
                    ));
                    return;
                }
            } else {
                $io->text([
                    sprintf('  - Action: %s (DRY-RUN)', $action),
                    '  - Invoice: [would be created]',
                ]);
                $stats['ddis_sent_whmcs'] += $ddiCount;
                $stats['total_whmcs_invoiced'] += $totalCost;
            }
        }

        $stats['companies_processed']++;
    }
}
