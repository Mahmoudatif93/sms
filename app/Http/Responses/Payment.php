<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


class Payment extends DataInterface
{
    public string $id;
    public string $organization_name;
    public string $service_name;
    public string $payemt_methode;
    public string $status;
    public string $amount;
    public string $sms_point;
    public ?string $invoice_file;
    public string $createdAt;
    public string $updatedAt;

    /**
     * PaymentResponse constructor.
     *
     * @param \App\Models\Payment $payment
     */
    public function __construct(\App\Models\Payment $payment)
    {
        $this->id = $payment->id;
        $this->organization_name = $payment->organization->name;
        if($payment->wallet){
            $this->service_name = $payment->wallet->service?->decription ?? '-';
        }elseif($payment->smsPlanTransaction){
            $this->service_name = \App\Enums\Service::SMS;
        }elseif($payment->chargeRequestBank){
            $this->service_name = $payment->chargeRequestBank->service?->description ?? '-';
        }else{
            $this->service_name  = "-";
        }

        $this->payemt_methode = $payment->paymentMethod->name;
        $this->status = $payment->payment_status;
        $this->amount = $payment->amount . ' '.$payment->currency; 
        if($payment->smsPlanTransaction){
            $this->sms_point = (string) ($payment->smsPlanTransaction->points_allocated ?? 0);
        }elseif($payment->chargeRequestBank){
            $this->sms_point = (string) ($payment->chargeRequestBank->points_cnt ?? 0);
        }else{
            $this->sms_point = '0';
        }
     
        $this->invoice_file = $payment->invoice_file;
    }

}
