<?php

// app/Rules/ValidGroupId.php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ValidGroupId implements Rule
{
    public function passes($attribute, $value)
    {
        // Check if the group_id is 0 or exists in the contact_group table
        return $value == 0 || DB::table('contact_group')->where('id', $value)->exists();
    }

    public function message()
    {
        return 'The selected group ID is invalid.';
    }
}
