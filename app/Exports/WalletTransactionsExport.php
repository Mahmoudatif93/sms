<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class WalletTransactionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
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
        return [
            __('message.transaction_id'),
            __('message.transaction_type'),
            __('message.category'),
            __('message.amount'),
            __('message.status'),
            __('message.description'),
            __('message.created_at'),
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->id,
            $transaction->transaction_type,
            $transaction->category,
            $transaction->amount,
            $transaction->status,
            $transaction->description,
            $transaction->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
