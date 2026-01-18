<?php

declare(strict_types=1);

/**
 * First Period Calculator - Prorated billing for DID purchases
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/FirstPeriodCalculator.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 *
 * Supports two renewal modes (set at Brand level):
 * - per_did: Each DID has independent renewal dates, full month charge
 * - consolidated: All DIDs renew on same anchor date, pro-rated first period
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use DateTimeInterface;
use DateTimeImmutable;

/**
 * Calculates the first period cost for DID purchases
 *
 * Billing modes:
 * - per_did: Full month charge, nextRenewalAt = purchaseDate + 1 month
 * - consolidated: Pro-rated to anchor date (or calendar month end for first DID)
 */
final class FirstPeriodCalculator
{
    public const MODE_PER_DID = 'per_did';
    public const MODE_CONSOLIDATED = 'consolidated';

    /**
     * Calculate the first period cost
     *
     * @param float $setupPrice One-time setup fee
     * @param float $monthlyPrice Monthly recurring fee
     * @param string $renewalMode Renewal mode: 'per_did' or 'consolidated'
     * @param DateTimeInterface|null $purchaseDate Purchase date (defaults to now)
     * @param DateTimeInterface|null $anchorDate Anchor date for consolidated mode (null = use calendar month end)
     * @return array{
     *     setupPrice: float,
     *     proratedMonthlyPrice: float,
     *     totalDueNow: float,
     *     daysInFirstPeriod: int,
     *     daysInMonth: int,
     *     nextRenewalDate: DateTimeImmutable,
     *     periodStart: DateTimeImmutable,
     *     periodEnd: DateTimeImmutable,
     *     isFullMonth: bool,
     *     renewalMode: string
     * }
     */
    public function calculate(
        float $setupPrice,
        float $monthlyPrice,
        string $renewalMode = self::MODE_PER_DID,
        ?DateTimeInterface $purchaseDate = null,
        ?DateTimeInterface $anchorDate = null
    ): array {
        $now = $purchaseDate
            ? DateTimeImmutable::createFromInterface($purchaseDate)
            : new DateTimeImmutable();

        // Period start is today
        $periodStart = $now->setTime(0, 0, 0);

        if ($renewalMode === self::MODE_PER_DID) {
            // per_did mode: Full month charge, renewal exactly 1 month from purchase
            return $this->calculatePerDidMode($setupPrice, $monthlyPrice, $periodStart);
        }

        // consolidated mode: Pro-rate to anchor or calendar month end
        return $this->calculateConsolidatedMode($setupPrice, $monthlyPrice, $periodStart, $anchorDate);
    }

    /**
     * Calculate for per_did mode
     *
     * Full month charge, nextRenewalAt = purchaseDate + 1 month
     */
    private function calculatePerDidMode(
        float $setupPrice,
        float $monthlyPrice,
        DateTimeImmutable $periodStart
    ): array {
        // Period end is exactly 1 month from now
        $nextRenewalDate = $periodStart->modify('+1 month');
        $periodEnd = $nextRenewalDate->modify('-1 day')->setTime(23, 59, 59);

        // Full month - no proration
        $proratedMonthlyPrice = $monthlyPrice;
        $totalDueNow = $setupPrice + $proratedMonthlyPrice;

        // Calculate days for display (approximate - it's a full month)
        $daysInMonth = (int) $periodStart->format('t');
        $daysInFirstPeriod = $daysInMonth;

        return [
            'setupPrice' => $setupPrice,
            'proratedMonthlyPrice' => $proratedMonthlyPrice,
            'totalDueNow' => round($totalDueNow, 2),
            'daysInFirstPeriod' => $daysInFirstPeriod,
            'daysInMonth' => $daysInMonth,
            'nextRenewalDate' => $nextRenewalDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'isFullMonth' => true,
            'renewalMode' => self::MODE_PER_DID,
        ];
    }

    /**
     * Calculate for consolidated mode
     *
     * Pro-rate to anchor date, or calendar month end if no anchor (first DID)
     */
    private function calculateConsolidatedMode(
        float $setupPrice,
        float $monthlyPrice,
        DateTimeImmutable $periodStart,
        ?DateTimeInterface $anchorDate
    ): array {
        if ($anchorDate !== null) {
            // Existing anchor - pro-rate to anchor date
            $anchor = DateTimeImmutable::createFromInterface($anchorDate);

            // Find the next occurrence of the anchor
            $dayOfAnchor = (int) $anchor->format('j');
            $currentDay = (int) $periodStart->format('j');

            if ($currentDay < $dayOfAnchor) {
                // Anchor is later this month
                $nextRenewalDate = $periodStart->setDate(
                    (int) $periodStart->format('Y'),
                    (int) $periodStart->format('n'),
                    $dayOfAnchor
                )->setTime(0, 0, 0);
            } else {
                // Anchor is next month
                $nextRenewalDate = $periodStart->modify('first day of next month')
                    ->setDate(
                        (int) $periodStart->modify('first day of next month')->format('Y'),
                        (int) $periodStart->modify('first day of next month')->format('n'),
                        min($dayOfAnchor, (int) $periodStart->modify('first day of next month')->format('t'))
                    )->setTime(0, 0, 0);
            }

            $periodEnd = $nextRenewalDate->modify('-1 day')->setTime(23, 59, 59);
        } else {
            // No anchor (first DID) - use calendar month end
            $periodEnd = $periodStart->modify('last day of this month')->setTime(23, 59, 59);
            $nextRenewalDate = $periodStart->modify('first day of next month')->setTime(0, 0, 0);
        }

        // Calculate days for proration
        $daysInFirstPeriod = (int) $periodStart->diff($nextRenewalDate)->days;
        if ($daysInFirstPeriod === 0) {
            $daysInFirstPeriod = 1; // At least 1 day
        }
        $daysInMonth = (int) $periodStart->format('t');

        // Pro-rate monthly price
        $dailyRate = $monthlyPrice / $daysInMonth;
        $proratedMonthlyPrice = round($dailyRate * $daysInFirstPeriod, 2);

        // Total due now
        $totalDueNow = $setupPrice + $proratedMonthlyPrice;

        // Check if this is effectively a full month
        $isFullMonth = ($daysInFirstPeriod >= $daysInMonth);

        return [
            'setupPrice' => $setupPrice,
            'proratedMonthlyPrice' => $proratedMonthlyPrice,
            'totalDueNow' => round($totalDueNow, 2),
            'daysInFirstPeriod' => $daysInFirstPeriod,
            'daysInMonth' => $daysInMonth,
            'nextRenewalDate' => $nextRenewalDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'isFullMonth' => $isFullMonth,
            'renewalMode' => self::MODE_CONSOLIDATED,
        ];
    }

    /**
     * Generate a preview of the purchase costs
     *
     * @param float $setupPrice One-time setup fee
     * @param float $monthlyPrice Monthly recurring fee
     * @param string $renewalMode Renewal mode: 'per_did' or 'consolidated'
     * @param DateTimeInterface|null $purchaseDate Purchase date (defaults to now)
     * @param DateTimeInterface|null $anchorDate Anchor date for consolidated mode
     * @return array{
     *     setupPrice: float,
     *     monthlyPrice: float,
     *     proratedFirstMonth: float,
     *     totalDueNow: float,
     *     nextRenewalDate: string,
     *     nextRenewalAmount: float,
     *     breakdown: array<int, array{description: string, amount: float}>,
     *     isFullMonth: bool,
     *     renewalMode: string
     * }
     */
    public function preview(
        float $setupPrice,
        float $monthlyPrice,
        string $renewalMode = self::MODE_PER_DID,
        ?DateTimeInterface $purchaseDate = null,
        ?DateTimeInterface $anchorDate = null
    ): array {
        $calculation = $this->calculate($setupPrice, $monthlyPrice, $renewalMode, $purchaseDate, $anchorDate);

        $breakdown = [];

        if ($setupPrice > 0) {
            $breakdown[] = [
                'description' => 'Setup fee',
                'amount' => $setupPrice,
            ];
        }

        // Different description based on mode
        if ($calculation['isFullMonth']) {
            $breakdown[] = [
                'description' => 'Monthly fee (full month)',
                'amount' => $calculation['proratedMonthlyPrice'],
            ];
        } else {
            $breakdown[] = [
                'description' => sprintf(
                    'Monthly fee (prorated: %d of %d days)',
                    $calculation['daysInFirstPeriod'],
                    $calculation['daysInMonth']
                ),
                'amount' => $calculation['proratedMonthlyPrice'],
            ];
        }

        return [
            'setupPrice' => $setupPrice,
            'monthlyPrice' => $monthlyPrice,
            'proratedFirstMonth' => $calculation['proratedMonthlyPrice'],
            'totalDueNow' => $calculation['totalDueNow'],
            'nextRenewalDate' => $calculation['nextRenewalDate']->format('Y-m-d'),
            'nextRenewalAmount' => $monthlyPrice,
            'breakdown' => $breakdown,
            'isFullMonth' => $calculation['isFullMonth'],
            'renewalMode' => $calculation['renewalMode'],
        ];
    }
}
