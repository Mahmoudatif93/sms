<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\UserOtp;
use Illuminate\Support\Str;

class UserOtpRepository implements UserOtpRepositoryInterface
{
    protected $UserOtp;

    public function __construct(UserOtp $UserOtp)
    {
        $this->UserOtp = $UserOtp;
    }

    public function findall($organizationId, $perPage, $search)
    {
        $search = $request->search ?? null;
        return  UserOtp::where('organization_id', $organizationId)->
        when(!empty($search), function ($query) use ($search) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->Where('mobile', 'like', '%' . $search . '%');
            });
        })
        ->orderBy('created_at', 'DESC')
        ->paginate($perPage);
    }


    public function find($id)
    {
        return $this->UserOtp->findOrFail($id);


    }


    public function create(array $data)
    {
        return $this->UserOtp->create($data);
    }

    public function update($id, array $data)
    {

        $UserOtp = $this->find($id);
        $UserOtp->update($data);
        return $UserOtp;
    }

    public function delete($id)
    {
        $UserOtp = $this->find($id);

        return $UserOtp->delete();
    }
}
