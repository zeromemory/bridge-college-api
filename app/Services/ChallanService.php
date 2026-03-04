<?php

namespace App\Services;

use App\Models\Application;
use App\Models\FeeChallan;

class ChallanService
{
    public function generate(Application $application, float $amount, string $dueDate): FeeChallan
    {
        return $application->challans()->create([
            'challan_number' => $this->generateChallanNumber(),
            'amount' => $amount,
            'due_date' => $dueDate,
            'status' => 'pending',
        ]);
    }

    public function markPaid(FeeChallan $challan, ?string $paymentReference = null): FeeChallan
    {
        $challan->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
        ]);

        return $challan;
    }

    private function generateChallanNumber(): string
    {
        $year = date('Y');

        do {
            $number = 'BCI-FEE-' . $year . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (FeeChallan::where('challan_number', $number)->exists());

        return $number;
    }
}
