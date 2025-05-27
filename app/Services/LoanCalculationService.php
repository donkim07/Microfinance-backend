<?php

namespace App\Services;

use Carbon\Carbon;

class LoanCalculationService
{
    /**
     * Calculate loan interest based on principal, term, and interest rate.
     *
     * @param float $principal
     * @param int $term
     * @param string $termPeriod
     * @param float $interestRate
     * @param string $interestType
     * @return float
     */
    public function calculateInterest($principal, $term, $termPeriod, $interestRate, $interestType)
    {
        // Convert interest rate to decimal
        $interestRate = $interestRate / 100;
        
        if ($interestType === 'FIXED') {
            // Calculate fixed interest
            $interest = $principal * $interestRate * $term;
            
            // Adjust for term period if yearly
            if ($termPeriod === 'YEAR') {
                $interest *= 12; // Convert annual rate to monthly
            }
            
            return round($interest, 2);
        } else { // REDUCING_BALANCE
            // Calculate monthly rate
            $monthlyRate = $interestRate;
            $totalMonths = $term;
            
            // Adjust for term period
            if ($termPeriod === 'DAY') {
                $monthlyRate = $interestRate * 30; // Approximate days to months
                $totalMonths = ceil($term / 30);
            } elseif ($termPeriod === 'WEEK') {
                $monthlyRate = $interestRate * 4; // Approximate weeks to months
                $totalMonths = ceil($term / 4);
            } elseif ($termPeriod === 'YEAR') {
                $monthlyRate = $interestRate / 12;
                $totalMonths = $term * 12;
            }
            
            $remainingPrincipal = $principal;
            $totalInterest = 0;
            $monthlyPrincipal = $principal / $totalMonths;
            
            // Calculate reducing balance interest for each period
            for ($i = 0; $i < $totalMonths; $i++) {
                $monthlyInterest = $remainingPrincipal * $monthlyRate;
                $totalInterest += $monthlyInterest;
                $remainingPrincipal -= $monthlyPrincipal;
            }
            
            return round($totalInterest, 2);
        }
    }
    
    /**
     * Calculate processing fee based on principal and fee settings.
     *
     * @param float $principal
     * @param float $processingFee
     * @param string $processingFeeType
     * @return float
     */
    public function calculateProcessingFee($principal, $processingFee, $processingFeeType)
    {
        if ($processingFeeType === 'FIXED') {
            return round($processingFee, 2);
        } else { // PERCENTAGE
            return round(($processingFee / 100) * $principal, 2);
        }
    }
    
    /**
     * Calculate insurance fee based on principal and fee settings.
     *
     * @param float $principal
     * @param float $insuranceFee
     * @param string $insuranceFeeType
     * @return float
     */
    public function calculateInsuranceFee($principal, $insuranceFee, $insuranceFeeType)
    {
        if ($insuranceFeeType === 'FIXED') {
            return round($insuranceFee, 2);
        } else { // PERCENTAGE
            return round(($insuranceFee / 100) * $principal, 2);
        }
    }
    
    /**
     * Calculate total fees.
     *
     * @param float $processingFee
     * @param float $insuranceFee
     * @return float
     */
    public function calculateTotalFees($processingFee, $insuranceFee)
    {
        return round($processingFee + $insuranceFee, 2);
    }
    
    /**
     * Calculate total loan amount.
     *
     * @param float $principal
     * @param float $interest
     * @param float $totalFees
     * @return float
     */
    public function calculateTotalAmount($principal, $interest, $totalFees)
    {
        return round($principal + $interest + $totalFees, 2);
    }
    
    /**
     * Calculate monthly installment.
     *
     * @param float $totalAmount
     * @param int $term
     * @param string $termPeriod
     * @return float
     */
    public function calculateInstallment($totalAmount, $term, $termPeriod)
    {
        $totalMonths = $term;
        
        // Adjust for term period
        if ($termPeriod === 'DAY') {
            $totalMonths = ceil($term / 30);
        } elseif ($termPeriod === 'WEEK') {
            $totalMonths = ceil($term / 4);
        } elseif ($termPeriod === 'YEAR') {
            $totalMonths = $term * 12;
        }
        
        return round($totalAmount / $totalMonths, 2);
    }
    
    /**
     * Calculate loan end date based on start date, term, and term period.
     *
     * @param string $startDate
     * @param int $term
     * @param string $termPeriod
     * @return string
     */
    public function calculateEndDate($startDate, $term, $termPeriod)
    {
        $date = Carbon::parse($startDate);
        
        if ($termPeriod === 'DAY') {
            $date->addDays($term);
        } elseif ($termPeriod === 'WEEK') {
            $date->addWeeks($term);
        } elseif ($termPeriod === 'MONTH') {
            $date->addMonths($term);
        } elseif ($termPeriod === 'YEAR') {
            $date->addYears($term);
        }
        
        return $date->format('Y-m-d');
    }
    
    /**
     * Generate amortization schedule.
     *
     * @param float $principal
     * @param int $term
     * @param string $termPeriod
     * @param float $interestRate
     * @param string $interestType
     * @param string $startDate
     * @return array
     */
    public function generateAmortizationSchedule($principal, $term, $termPeriod, $interestRate, $interestType, $startDate)
    {
        // Convert interest rate to decimal
        $interestRate = $interestRate / 100;
        
        // Calculate monthly rate and total months
        $monthlyRate = $interestRate;
        $totalMonths = $term;
        
        // Adjust for term period
        if ($termPeriod === 'DAY') {
            $monthlyRate = $interestRate * 30; // Approximate days to months
            $totalMonths = ceil($term / 30);
        } elseif ($termPeriod === 'WEEK') {
            $monthlyRate = $interestRate * 4; // Approximate weeks to months
            $totalMonths = ceil($term / 4);
        } elseif ($termPeriod === 'YEAR') {
            $monthlyRate = $interestRate / 12;
            $totalMonths = $term * 12;
        }
        
        $remainingPrincipal = $principal;
        $schedule = [];
        $date = Carbon::parse($startDate);
        
        // Calculate EMI for reducing balance
        $emi = 0;
        if ($interestType === 'REDUCING_BALANCE') {
            $emi = $this->calculateEMI($principal, $monthlyRate, $totalMonths);
        } else {
            // For fixed interest, divide total amount by number of periods
            $totalInterest = $principal * $interestRate * $term;
            if ($termPeriod === 'YEAR') {
                $totalInterest *= 12;
            }
            $totalAmount = $principal + $totalInterest;
            $emi = $totalAmount / $totalMonths;
        }
        
        for ($i = 1; $i <= $totalMonths; $i++) {
            if ($termPeriod === 'MONTH') {
                $date->addMonth();
            } elseif ($termPeriod === 'WEEK') {
                $date->addWeeks(4);
            } elseif ($termPeriod === 'DAY') {
                $date->addDays(30);
            } elseif ($termPeriod === 'YEAR') {
                $date->addMonth();
            }
            
            if ($interestType === 'REDUCING_BALANCE') {
                $monthlyInterest = $remainingPrincipal * $monthlyRate;
                $monthlyPrincipal = $emi - $monthlyInterest;
                
                // Adjust for final payment
                if ($i === $totalMonths) {
                    $monthlyPrincipal = $remainingPrincipal;
                    $emi = $monthlyPrincipal + $monthlyInterest;
                }
                
                $remainingPrincipal -= $monthlyPrincipal;
                
                // Ensure remaining principal doesn't go below zero
                if ($remainingPrincipal < 0) {
                    $remainingPrincipal = 0;
                }
            } else { // FIXED
                $monthlyInterest = ($principal * $interestRate * $term) / $totalMonths;
                if ($termPeriod === 'YEAR') {
                    $monthlyInterest = ($principal * $interestRate * $term * 12) / $totalMonths;
                }
                $monthlyPrincipal = $principal / $totalMonths;
                $remainingPrincipal -= $monthlyPrincipal;
            }
            
            $schedule[] = [
                'payment_number' => $i,
                'payment_date' => $date->format('Y-m-d'),
                'beginning_balance' => round($remainingPrincipal + $monthlyPrincipal, 2),
                'scheduled_payment' => round($emi, 2),
                'principal_payment' => round($monthlyPrincipal, 2),
                'interest_payment' => round($monthlyInterest, 2),
                'ending_balance' => round($remainingPrincipal, 2),
            ];
        }
        
        return $schedule;
    }
    
    /**
     * Calculate Equated Monthly Installment (EMI) for reducing balance method.
     *
     * @param float $principal
     * @param float $monthlyRate
     * @param int $totalMonths
     * @return float
     */
    private function calculateEMI($principal, $monthlyRate, $totalMonths)
    {
        $emi = $principal * $monthlyRate * pow(1 + $monthlyRate, $totalMonths);
        $emi /= pow(1 + $monthlyRate, $totalMonths) - 1;
        
        return round($emi, 2);
    }
} 