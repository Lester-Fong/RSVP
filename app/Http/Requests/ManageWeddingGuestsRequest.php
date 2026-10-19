<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ManageWeddingGuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('wedding') instanceof Wedding
            && ($this->user()?->can('update', $this->route('wedding')) ?? false);
    }

    public function rules(): array
    {
        return [];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->except('_token', '_method')), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'This field is not supported by guest management.');
            }
        }];
    }
}
