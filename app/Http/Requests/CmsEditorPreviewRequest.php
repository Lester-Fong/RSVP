<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CmsEditorPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $wedding = $this->route('wedding');

        return $wedding instanceof Wedding && ($this->user()?->can('update', $wedding) ?? false);
    }

    public function rules(): array
    {
        return $this->routeIs('accounts.weddings.cms-preview.upload')
            ? ['image' => ['required', 'file']]
            : ['document' => ['required', 'array'], 'slots' => ['required', 'array:hero,details,story'],
                'slots.details' => ['sometimes', 'nullable', 'array'],
                'slots.hero' => ['present', 'nullable', 'array'], 'slots.story' => ['present', 'nullable', 'array']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = $this->routeIs('accounts.weddings.cms-preview.upload') ? ['image'] : ['document', 'slots'];
            if (array_diff(array_keys($this->all()), $allowed)) {
                $validator->errors()->add('request', 'Only the requested editor fields are allowed.');
            }
        }];
    }
}
