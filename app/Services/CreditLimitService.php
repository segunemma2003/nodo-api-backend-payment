<?php

namespace App\Services;

use App\Models\Customer;

class CreditLimitService
{
    /**
     * Calculate credit limit based on minimum purchase and payment plan
     * Formula: Credit Limit = Minimum Purchase × (Payment Plan + 1)
     */
    public function calculateCreditLimit(float $minimumPurchase, int $paymentPlanDuration): float
    {
        return $minimumPurchase * ($paymentPlanDuration + 1);
    }

    /**
     * Update customer credit limit
     */
    public function updateCustomerCreditLimit(Customer $customer): void
    {
        $creditLimit = $this->calculateCreditLimit(
            $customer->minimum_purchase_amount,
            $customer->payment_plan_duration
        );

        $customer->credit_limit = $creditLimit;
        $customer->updateBalances();
    }
}

