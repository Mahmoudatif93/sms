<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithMapping;

class FavoriteMessagesExport implements WithMultipleSheets, WithMapping
{
    use Exportable;

    protected $query;
    protected $chunkSize;
    public function __construct(Builder $query, int $chunkSize = 100000)
    {
        $this->query = $query;
        $this->chunkSize = $chunkSize;
    }

    public function sheets(): array
    {
        $totalRecords = $this->query->count();
        $sheets = [];
        $offset = 0;

        while ($offset < $totalRecords) {
            // Clone the query, apply offset and limit
            $queryForChunk = (clone $this->query)->offset($offset)->limit($this->chunkSize);
                $sheets[] = new FavoriteMessagesSheetExport($queryForChunk);

            // Update offset for the next chunk
            $offset += $this->chunkSize;
        }

        return $sheets;
    }


    public function map($row): array
    {


        return [
            $row->text
        ];
    }
}
