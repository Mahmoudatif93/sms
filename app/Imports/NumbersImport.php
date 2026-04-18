<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class NumbersImport implements ToCollection
{
    protected $numbers = [];

    public function collection(Collection $rows)
    {
        $skipHeader = true;

        foreach ($rows as $row) {
            if ($skipHeader) {
                // Skip the first row (header)
                $skipHeader = false;
                continue;
            }

            // Assuming numbers are in the first column
            $number = $row[0];

            // Validate the number format
            if (!preg_match('/^\d{10,15}$/', $number)) {
                throw ValidationException::withMessages([
                    'file' => "The number '{$number}' is invalid. It must be a numeric value and within 10-15 digits."
                ]);
            }

            $this->numbers[] = $number;
        }

        // Validate the total number of entries
        /* if (count($this->numbers) > 250) {
             throw ValidationException::withMessages([
                 'file' => 'The Excel file must not contain more than 250 numbers.'
             ]);
         }*/
    }

    public function getNumbers()
    {
        return $this->numbers;
    }
}
