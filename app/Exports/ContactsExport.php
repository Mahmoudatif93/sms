<?php

namespace App\Exports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactsExport implements FromCollection, WithHeadings
{
    private $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }
    public function collection()
    {
        return Contact::where('user_id', $this->user_id)->get();
    }

    public function headings(): array
    {
        return [
            'user_id',
            'group_id',
            'number',
            'name'
        ];
    }
}
