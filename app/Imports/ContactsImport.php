<?php

namespace App\Imports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use App\Rules\ValidGroupId;

class ContactsImport implements ToModel, WithValidation, WithHeadingRow
{


    private $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }


    public function model(array $row)
    {
        return new Contact([
            'user_id' => $this->user_id,
            'group_id' => $row['group_id'],
            'number' => $row['number'],
            'name' => $row['name'],
        ]);
    }

    public function rules(): array
    {

        return [
            'group_id' => ['required', new ValidGroupId()],
            'number' => 'required|numeric|digits_between:5,20',
            'name' => 'required|string|max:100',
        ];
    }
}
