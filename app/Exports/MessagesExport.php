<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MessagesExport implements WithMultipleSheets
{
    use Exportable;

    protected $query;
    protected $chunkSize;
    protected $messageId;
    protected $exportType;
    public function __construct(Builder $query, int $chunkSize = 100000, $messageId,$exportType)
    {
        $this->query = $query;
        $this->chunkSize = $chunkSize;
        $this->messageId = $messageId;
        $this->exportType = $exportType;
    }

    public function sheets(): array
    {
        $totalRecords = $this->query->count();
        $sheets = [];
        $offset = 0;

        while ($offset < $totalRecords) {
            // Clone the query, apply offset and limit
            $queryForChunk = (clone $this->query)->offset($offset)->limit($this->chunkSize);
            if ($this->messageId == null) {
                ($this->exportType === 'details')
                ? $sheets[] = new MessagesDetailsSheetExport($queryForChunk)
                : $sheets[] = new MessagesSheetExport($queryForChunk);
            } else {
                $sheets[] = new MessagesDetailsSheetExport($queryForChunk);
            }
            // Update offset for the next chunk
            $offset += $this->chunkSize;
        }

        return $sheets;
    }
}
