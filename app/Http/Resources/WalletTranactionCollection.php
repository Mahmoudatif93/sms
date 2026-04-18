<?php

namespace App\Http\Resources;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WalletTranactionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function($item) {
                return [
                    'id' => $item->id,
                    'transaction_type' => WalletTransactionType::getDescription($item->transaction_type),
                    'status' => WalletTransactionStatus::getDescription($item->status),
                    'amount' => $item->amount .($item->wallet->service->name == "sms" ? " Points" : " SAR"),    
                    'serivce_name' => $item->wallet->service->name,
                    'description' => $item->description,
                    'created_at' =>  $item->created_at,
                ];
            }),
            'pagination' => [
                'total' => $this->resource->total(),
                'count' => $this->resource->count(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'total_pages' => $this->resource->lastPage(),
            ],
        ];
    }
}
