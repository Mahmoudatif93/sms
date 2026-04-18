<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlanSheetExport implements FromCollection, WithHeadings
{
    protected Collection $plans;

    public function __construct(Collection $plans)
    {
        $this->plans = $plans;
    }

    public function collection()
    {
        return $this->plans->map(function ($plan) {
            return [
                'ID'          => $plan->id,
                'Points Count' => $plan->points_cnt,
                'Price'       => $plan->price,
                'Currency'    => $plan->currency,
                'Method'      => $plan->method,
                'Date'        => $plan->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Points Count',
            'Price',
            'Currency',
            'Method',
            'Date',
        ];
    }
}
