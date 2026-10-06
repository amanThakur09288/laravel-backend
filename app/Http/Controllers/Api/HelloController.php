<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HelloController extends Controller
{
   public function hello()
    {
        return response()->json([
            'message' => 'Hello from Laravel Backend'
        ]);
    }
}
