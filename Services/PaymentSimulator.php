<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Env;

/**
 * Stands in for a real mobile-money/card gateway. No live integration —
 * outcomes are decided by a weighted coin flip (PAYMENT_SIMULATOR_SUCCESS_RATE)
 * so the app can demonstrate the full pay -> callback -> receipt lifecycle,
 * including failures, without a sandbox account.
 */
class PaymentSimulator
{
    public function generateReference(string $prefix = 'SIM'): string
    {
        return sprintf('%s-%s-%s', $prefix, date('Ymd'), strtoupper(bin2hex(random_bytes(5))));
    }

    public function decideOutcome(): bool
    {
        $successRate = (float) Env::get('PAYMENT_SIMULATOR_SUCCESS_RATE', 0.85);
        $successRate = min(1.0, max(0.0, $successRate));
        return (random_int(0, 1_000_000) / 1_000_000) <= $successRate;
    }
}
