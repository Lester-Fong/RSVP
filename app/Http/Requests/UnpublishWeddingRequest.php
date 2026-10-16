<?php

namespace App\Http\Requests;

class UnpublishWeddingRequest extends PublishWeddingRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'publication_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
