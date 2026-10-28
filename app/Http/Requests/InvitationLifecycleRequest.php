<?php

namespace App\Http\Requests;

use App\Exceptions\PersonalRsvpValidationException;
use Illuminate\Contracts\Validation\Validator;

class InvitationLifecycleRequest extends ManageWeddingGuestsRequest
{
    public function rules(): array
    {
        return [
            'expectedState' => ['required', 'string', 'in:active,withdrawn'],
            'expectedGuests' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'expectedGuests.*' => ['required', 'array:guestId,accessVersion,responseVersion,linkVersion'],
            'expectedGuests.*.guestId' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'expectedGuests.*.accessVersion' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expectedGuests.*.responseVersion' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'expectedGuests.*.linkVersion' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw PersonalRsvpValidationException::because('rsvp_access', 'Use the current invitation details and supported fields.');
    }
}
