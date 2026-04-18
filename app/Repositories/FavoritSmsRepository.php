<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\Favorit;
use Illuminate\Support\Str;

class FavoritSmsRepository implements FavoritSmsRepositoryInterface
{
    protected $Favorit;

    public function __construct(Favorit $Favorit)
    {
        $this->Favorit = $Favorit;
    }


    public function findAll($workspace_id, $perPage = null, $search = null)
    {
        $query = Favorit::where('workspace_id', $workspace_id)
            ->when(!empty($search), fn($q) => $q->where('text', 'like', "%$search%"))
            ->orderByDesc('id');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }


    public function find($id)
    {
        return $this->Favorit->findOrFail($id);
    }


    public function create(array $data)
    {

        return $this->Favorit->create($data);
    }

    public function update($id, array $data)
    {

        $Favorit = $this->find($id);
        $Favorit->update($data);
        return $Favorit;
    }
    public function delete($id)
    {
        $Favorit = $this->find($id);

        return $Favorit->delete();
    }
}
