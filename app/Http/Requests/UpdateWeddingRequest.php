<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeddingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'partner_one_name' => ['required', 'string', 'max:255'],
            'partner_two_name' => ['required', 'string', 'max:255'],
            'wedding_date' => ['nullable', 'date'],
            'wedding_time' => ['nullable', 'date_format:H:i'],
            'ceremony_venue' => ['nullable', 'string', 'max:500'],
            'reception_venue' => ['nullable', 'string', 'max:500'],
        ];
    }
}
