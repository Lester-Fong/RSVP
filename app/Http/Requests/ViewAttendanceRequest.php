<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ViewAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('wedding') instanceof Wedding
            && ($this->user()?->can('view', $this->route('wedding')) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,attending,declined,pending'],
            'view' => ['nullable', 'in:guests,groups'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array{search: string, status: string, view: string} */
    public function filters(): array
    {
        return ['search' => trim($this->validated('search') ?? ''),
            'status' => $this->validated('status') ?? 'all', 'view' => $this->validated('view') ?? 'guests'];
    }
}
