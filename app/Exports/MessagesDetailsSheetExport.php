<?php
namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Crypt;
use App\Helpers\EncryptionHelper;
class MessagesDetailsSheetExport implements FromQuery, WithHeadings, WithMapping
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
            __('message.cost'),
            __('message.status'),
            __('message.sending_time'),
            __('message.creation_time'),
        ];
    }

    public function map($row): array
    {
        // Here we handle the decryption of the message text if needed
        if ($row->encrypted == 1 && !is_null($row->text) && $row->variables_message == 0) {
            try {
                $row->text = Crypt::decryptString($row->text);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                \Log::error("Decryption failed for text: " . $row->text);
                $row->text = '[Decryption Error]';
            }
        }

        return [
            $row->sender_name,
            $row->text,
            $row->cost,
            $row->number,
            $row->status,
            $row->creation_datetime,
            $row->updation_datetime
        ];
    }
}
