<?php

namespace App\Http\Requests;

use App\Exceptions\PersonalRsvpValidationException;
use Illuminate\Contracts\Validation\Validator;

class PersonalCredentialRequest extends ManageWeddingGuestsRequest
{
    public function rules(): array
    {
        return ['expectedVersion' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expectedAccessVersion' => ['required', 'integer', 'min:0', 'max:4294967295']];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw PersonalRsvpValidationException::because('rsvp_access', 'Use the current RSVP access values and supported fields.');
    }
}
