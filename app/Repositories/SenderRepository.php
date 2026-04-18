<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\Sender;
use Illuminate\Support\Str;

class SenderRepository implements SenderRepositoryInterface
{
    protected $Senders;

    public function __construct(Sender $Senders)
    {
        $this->Senders = $Senders;
    }



    public function findbyid($id)
    {
        return  Sender::where('id', $id)->orderBy('created_at','DESC')->first();
    }


    public function find($user_id)
    {
        return  Sender::where('user_id', $user_id)->orderBy('created_at','DESC')->get();
    }


    public function create(array $data)
    {

     //   $data = $this->handleFileUploads($data);
    // dd($data);
        return $this->Senders->create($data);
    }

    public function update($id, array $data)
    {

        $Senders = $this->findbyid($id);
       // $data = $this->handleFileUploads($data, $Senders);
        $Senders->update($data);
        return $Senders;
    }




    public function delete($id)
    {
        $Senders = $this->findbyid($id);
        if(!empty($Senders)){
            return $Senders->delete();
        }else{
            return 'no data found for this id';
        }

    }




    protected function handleFileUploads(array $data, $model = null)
    {

        $userId = auth()->id();
        foreach ($data as $key => $value) {
            if (is_file($value)) {
                $folderName = 'uploads/' . $userId;
                $fileName = $value->getClientOriginalName();
                $filePath = $value->storeAs($folderName, $fileName, 'public');
                $data[$key] = Storage::url($filePath);
            }
        }
        return $data;
    }
}
