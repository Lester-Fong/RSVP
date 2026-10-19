<?php

namespace App\Http\Requests;

use App\Models\InvitationGroup;

class StoreInvitationGroupRequest extends UpdateInvitationGroupRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'guests' => ['required', 'array', 'list', 'min:1', 'max:'.InvitationGroup::MAX_GUESTS],
            'guests.*' => ['required', 'array:name'],
            'guests.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'guests.required' => 'Add at least one named guest to this group.',
            'guests.*.name.required' => 'Enter a name for each guest.',
        ];
    }
}
