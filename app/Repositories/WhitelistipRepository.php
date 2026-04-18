<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\Whitelistip;
use Illuminate\Support\Str;

class WhitelistipRepository implements WhitelistipRepositoryInterface
{
    protected $Whitelistip;

    public function __construct(Whitelistip $Whitelistip)
    {
        $this->Whitelistip = $Whitelistip;
    }

    public function findall($organizationId, $perPage, $search)
    {

        return  Whitelistip::where('organization_id', $organizationId)

            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->Where('name', 'like', '%' . $search . '%')
                        ->orWhere('ip', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage);

        $search = $request->search ?? null;
    }


    public function find($id)
    {
        return $this->Whitelistip->findOrFail($id);
    }


    public function create(array $data)
    {
        return $this->Whitelistip->create($data);
    }

    public function update($id, array $data)
    {

        $Whitelistip = $this->find($id);
        $Whitelistip->update($data);
        return $Whitelistip;
    }

    public function delete($id)
    {
        $Whitelistip = $this->find($id);

        return $Whitelistip->delete();
    }
}
