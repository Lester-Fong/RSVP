<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveCmsDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('wedding') instanceof Wedding && $this->user()?->can('update', $this->route('wedding'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document' => ['required', 'array'],
            'draft_version' => ['required', 'integer:strict', 'min:0', 'max:4294967294'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['document', 'draft_version'])) {
                $validator->errors()->add('request', 'Only draft content and its current save version are allowed.');
            }
        }];
    }
}
