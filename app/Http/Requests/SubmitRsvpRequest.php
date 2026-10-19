<?php

namespace App\Http\Requests;

use App\Models\InvitationGroup;
use App\Services\InvitationRsvp;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SubmitRsvpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        app(InvitationRsvp::class)->assertAvailable((string) $this->route('token'));

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'responses' => ['required', 'array', 'list', 'min:1', 'max:'.InvitationGroup::MAX_GUESTS],
            'responses.*' => ['required', 'array:guest,status'],
            'responses.*.guest' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D', 'distinct'],
            'responses.*.status' => ['required', 'string', 'in:attending,declined'],
        ];
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['version', 'responses', '_token']) !== []) {
                $validator->errors()->add('responses', 'Unsupported fields.');
            }
        }];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw ValidationException::withMessages(['rsvp' => 'Choose Attending or Declining for at least one invited person. Use only the choices shown in this invitation. Your saved responses have not changed.']);
    }
}
