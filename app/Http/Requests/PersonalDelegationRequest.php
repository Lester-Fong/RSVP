<?php

namespace App\Http\Requests;

use App\Exceptions\PersonalRsvpValidationException;
use Illuminate\Contracts\Validation\Validator;

class PersonalDelegationRequest extends ManageWeddingGuestsRequest
{
    public function rules(): array
    {
        return ['representativeId' => ['present', 'nullable', 'integer', 'min:1'],
            'expectedAccessVersions' => ['required', 'array', 'min:1', 'max:3'],
            'expectedAccessVersions.*' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expectedDependentLinkVersion' => ['required', 'integer', 'min:0', 'max:4294967295']];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw PersonalRsvpValidationException::because('rsvp_access', 'Use the current representative and RSVP access values.');
    }
}
