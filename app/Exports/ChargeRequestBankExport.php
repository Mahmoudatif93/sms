<?php

namespace App\Exports;

use App\Models\ChargeRequestBank;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ChargeRequestBankExport implements FromCollection, WithHeadings
{
    private $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }
    public function collection()
    {
        return ChargeRequestBank::where('user_id', $this->user_id)->get();
    }

    public function headings(): array
    {
        return [
            'receipt_attach', 'invoice_file', 'paymentreceipt',
            'points_cnt', 'amount', 'currency', 'bank_name', 'account_number', 'account_owner_name', 'deposit_date', 'request_date', 'status', 'type'

        ];
    }



    
}
