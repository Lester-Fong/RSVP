<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishWeddingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('wedding') instanceof Wedding && ($this->user()?->can('update', $this->route('wedding')) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'draft_version' => ['required', 'integer', 'min:0'],
            'publication_version' => ['required', 'integer', 'min:0'],
            'snapshot_token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->except('_token', '_method')), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'This field is not supported by publication.');
            }
        }];
    }
}
