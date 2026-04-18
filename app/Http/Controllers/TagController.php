<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Models\Organization;
use App\Models\Tag;
use DB;
class TagController extends BaseApiController implements HasMiddleware
{

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }
    public function index(Request $request, Organization $organization)
    {
      
    }
}
