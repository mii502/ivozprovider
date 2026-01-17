<?php

declare(strict_types=1);

/**
 * First Period Calculator - Prorated billing for DID purchases
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/FirstPeriodCalculator.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use DateTimeInterface;
use DateTimeImmutable;

/**
 * Calculates the prorated first period cost for DID purchases
 *
 * Billing model:
 * - Setup fee: Charged in full immediately
 * - Monthly fee: Prorated for remaining days in the first month
 * - Renewal: Full monthly fee on the 1st of each subsequent month
 */
final class FirstPeriodCalculator
{
    /**
     * Calculate the prorated first period cost
     *
     * @param float $setupPrice One-time setup fee
     * @param float $monthlyPrice Monthly recurring fee
     * @param DateTimeInterface|null $purchaseDate Purchase date (defaults to now)
     * @return array{
     *     setupPrice: float,
     *     proratedMonthlyPrice: float,
     *     totalDueNow: float,
     *     daysInFirstPeriod: int,
     *     daysInMonth: int,
     *     nextRenewalDate: DateTimeImmutable,
     *     periodStart: DateTimeImmutable,
     *     periodEnd: DateTimeImmutable
     * }
     */
    public function calculate(
        float $setupPrice,
        float $monthlyPrice,
        ?DateTimeInterface $purchaseDate = null
    ): array {
        $now = $purchaseDate
            ? DateTimeImmutable::createFromInterface($purchaseDate)
            : new DateTimeImmutable();

        // Period start is today
        $periodStart = $now->setTime(0, 0, 0);

        // Period end is the last day of the current month
        $periodEnd = $now->modify('last day of this month')->setTime(23, 59, 59);

        // Next renewal is the 1st of next month
        $nextRenewalDate = $now->modify('first day of next month')->setTime(0, 0, 0);

        // Calculate days
        $daysInMonth = (int) $now->format('t');
        $dayOfMonth = (int) $now->format('j');
        $daysRemaining = $daysInMonth - $dayOfMonth + 1; // Including today

        // Prorate monthly price
        $dailyRate = $monthlyPrice / $daysInMonth;
        $proratedMonthlyPrice = round($dailyRate * $daysRemaining, 2);

        // Total due now
        $totalDueNow = $setupPrice + $proratedMonthlyPrice;

        return [
            'setupPrice' => $setupPrice,
            'proratedMonthlyPrice' => $proratedMonthlyPrice,
            'totalDueNow' => round($totalDueNow, 2),
            'daysInFirstPeriod' => $daysRemaining,
            'daysInMonth' => $daysInMonth,
            'nextRenewalDate' => $nextRenewalDate,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ];
    }

    /**
     * Generate a preview of the purchase costs
     *
     * @param float $setupPrice One-time setup fee
     * @param float $monthlyPrice Monthly recurring fee
     * @param DateTimeInterface|null $purchaseDate Purchase date (defaults to now)
     * @return array{
     *     setupPrice: float,
     *     monthlyPrice: float,
     *     proratedFirstMonth: float,
     *     totalDueNow: float,
     *     nextRenewalDate: string,
     *     nextRenewalAmount: float,
     *     breakdown: array<int, array{description: string, amount: float}>
     * }
     */
    public function preview(
        float $setupPrice,
        float $monthlyPrice,
        ?DateTimeInterface $purchaseDate = null
    ): array {
        $calculation = $this->calculate($setupPrice, $monthlyPrice, $purchaseDate);

        $breakdown = [];

        if ($setupPrice > 0) {
            $breakdown[] = [
                'description' => 'Setup fee',
                'amount' => $setupPrice,
            ];
        }

        $breakdown[] = [
            'description' => sprintf(
                'Monthly fee (prorated: %d of %d days)',
                $calculation['daysInFirstPeriod'],
                $calculation['daysInMonth']
            ),
            'amount' => $calculation['proratedMonthlyPrice'],
        ];

        return [
            'setupPrice' => $setupPrice,
            'monthlyPrice' => $monthlyPrice,
            'proratedFirstMonth' => $calculation['proratedMonthlyPrice'],
            'totalDueNow' => $calculation['totalDueNow'],
            'nextRenewalDate' => $calculation['nextRenewalDate']->format('Y-m-d'),
            'nextRenewalAmount' => $monthlyPrice,
            'breakdown' => $breakdown,
        ];
    }
}
