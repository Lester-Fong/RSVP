<?php

namespace App\Http\Requests;

use App\Exceptions\PersonalRsvpValidationException;
use App\Models\InvitationGroup;
use App\Services\PersonalRsvp;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPersonalRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(PersonalRsvp::class)->read((string) $this->route('token'));

        return true;
    }

    public function rules(): array
    {
        return ['access_version' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'responses' => ['required', 'array', 'list', 'min:1', 'max:'.InvitationGroup::MAX_GUESTS],
            'responses.*' => ['required', 'array:guest,version,status'],
            'responses.*.guest' => ['required', 'string', 'regex:/\\A[a-f0-9]{64}\\z/D', 'distinct:strict'],
            'responses.*.version' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'responses.*.status' => ['required', 'string', 'in:attending,declined']];
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['access_version', 'responses', '_token'])) {
                $validator->errors()->add('rsvp', 'Unsupported fields.');
            }
        }];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw PersonalRsvpValidationException::because('rsvp', 'Use only the people, choices and versions shown in this invitation.');
    }
}
