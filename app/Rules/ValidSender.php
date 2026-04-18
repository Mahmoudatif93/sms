<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class ValidSender implements ValidationRule
{
    private $organization_id;

    public function __construct($organization_id)
    {
        $this->organization_id = $organization_id;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // TODO : recive Dreams from Settings 
        $senderExists = false;
        if($value == "Dreams" &&  DB::table('sender')->where('organization_id', $this->organization_id)->count()==0){
            $senderExists = true;
        }else{
            $senderExists = DB::table('sender')
            ->where('organization_id', $this->organization_id)
            ->where('status', 1)
            ->where('name',$value)
            ->exists();
        }
        if(!$senderExists){
            $fail('validation.exists')->translate();
        }
    }

}
