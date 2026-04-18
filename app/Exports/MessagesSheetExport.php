<?php
namespace App\Exports;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Crypt;
use App\Helpers\EncryptionHelper;
use Maatwebsite\Excel\Concerns\WithMapping;

class MessagesSheetExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }




    public function headings(): array
    {
        // Define the column headings
        return [
            __('message.sender_name'),
            __('text_sms'),
            __('channel'),
            __('message.status'),
            __('message.cost'),
            __('message.count'),
            __('message.sending_time'),
            __('message.creation_time'),
        ];
    }



    public function map($row): array
    {


        return [
            $row->sender_name,
            $row->text,
            $row->channel,
            $row->status,
            $row->cost,
            $row->count,
            $row->sending_datetime,
            $row->creation_datetime,
        ];
    }

}
