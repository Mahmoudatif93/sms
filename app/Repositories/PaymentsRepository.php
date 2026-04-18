<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\Payments;
use Illuminate\Support\Str;

class PaymentsRepository implements PaymentsRepositoryInterface
{
    protected $Payments;

    public function __construct(Payments $Payments)
    {
        $this->Payments = $Payments;
    }

    public function findall($user_id, $perPage, $search = null)
    {

        if ($search != null) {
            return  Payments::where('user_id', $user_id)

                ->where(function ($query) use ($search) {
                    $query->where('response_code', 'like', '%' . $search . '%')
                        ->orWhere('card_brand', 'like', '%' . $search . '%')
                        ->orWhere('amount', 'like', '%' . $search . '%')
                        ->orWhere('currency', 'like', '%' . $search . '%')
                        ->orWhere('masked_pan', 'like', '%' . $search . '%');
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        } else {
            return  Payments::where('user_id', $user_id)->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        }
    }
}
