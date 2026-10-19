<?php

namespace App\Http\Requests;

class StoreGuestRequest extends ManageWeddingGuestsRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
