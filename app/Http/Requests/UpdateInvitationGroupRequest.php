<?php

namespace App\Http\Requests;

class UpdateInvitationGroupRequest extends ManageWeddingGuestsRequest
{
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'email', 'max:254'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
