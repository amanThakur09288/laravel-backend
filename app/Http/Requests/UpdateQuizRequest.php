<?php

namespace App\Http\Requests;

class UpdateQuizRequest extends StoreQuizRequest
{
    /*
    |--------------------------------------------------------------------------
    | Quiz updates use the same complete structure as creation.
    |--------------------------------------------------------------------------
    |
    | This keeps the quiz consistent.
    |
    | We don't allow partial question updates here.
    | The service replaces the quiz questions atomically.
    |
    */
}