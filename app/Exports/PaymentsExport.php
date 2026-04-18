<?php

namespace App\Exports;

use App\Models\Payments;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentsExport implements FromCollection, WithHeadings
{
    private $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }
    public function collection()
    {
        return Payments::where('user_id', $this->user_id)->get();
    }

    public function headings(): array
    {
        return [
          'payment_id','transaction_id','status',
    'track_id','response_code','response_hash','card_brand','amount','currency','masked_pan','payment_type','invoice_file','type'
        ];
    }
}
