<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class InvitationLinkRequest extends ManageWeddingGuestsRequest
{
    public function rules(): array
    {
        return ['link_version' => ['required', 'integer', 'min:0']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->except('_token', '_method')), ['link_version'])) {
                $validator->errors()->add('invitation_link', 'This request contains unsupported fields.');
            }
        }];
    }
}
