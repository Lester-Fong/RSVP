<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use JsonException;

class UpdateWeddingInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (str_starts_with((string) $this->header('Content-Type'), 'multipart/form-data') && is_string($this->input('content'))) {
            try {
                $content = $this->input('content');
                if (strlen($content) <= 16384) {
                    $this->merge(['content' => json_decode($content, true, 8, JSON_THROW_ON_ERROR)]);
                }
            } catch (JsonException) {
                // Existing array validation rejects malformed JSON.
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $wedding = $this->route('wedding');

        return $wedding instanceof Wedding && $this->user()->can('update', $wedding);
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
            'hero_photo_action' => ['sometimes', 'required', 'in:keep,replace,remove'],
            'hero_photo' => ['bail', 'required_if:hero_photo_action,replace', 'prohibited_unless:hero_photo_action,replace', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'content' => ['required', 'array:introduction_message,closing_message,sections'],
            'content.introduction_message' => ['required', 'string', 'max:500', 'not_regex:/[<>]/u'],
            'content.closing_message' => ['required', 'string', 'max:500', 'not_regex:/[<>]/u'],
            'content.sections' => ['required', 'array:ceremony,reception,closing'],
            'content.sections.ceremony' => ['required', 'boolean:strict'],
            'content.sections.reception' => ['required', 'boolean:strict'],
            'content.sections.closing' => ['required', 'boolean:strict'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->except('_token', '_method')), ['content', 'draft_version', 'hero_photo_action', 'hero_photo']) as $key) {
                $validator->errors()->add($key, 'This field is not supported by the invitation editor.');
            }

            if (! $validator->errors()->has('hero_photo') && $this->hasFile('hero_photo')) {
                $size = @getimagesize($this->file('hero_photo')->getPathname());
                if (! $size || $size[0] * $size[1] > 12000000) {
                    $validator->errors()->add('hero_photo', 'Choose an image with no more than 12 million pixels.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $photo = $this->file('hero_photo');

        return [
            'hero_photo.uploaded' => match ($photo instanceof UploadedFile ? $photo->getError() : null) {
                UPLOAD_ERR_INI_SIZE => 'This photo exceeds the server upload limit. Choose a photo up to 5 MiB; if it already meets that limit, please contact support. Your saved draft has not changed.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not accept your photo. Please contact support. Your saved draft has not changed.',
                UPLOAD_ERR_FORM_SIZE => 'Choose a JPEG, PNG, or WebP photo up to 5 MiB. Your saved draft has not changed.',
                default => 'Your photo did not finish uploading. Select it again and retry. Your saved draft has not changed.',
            },
            'hero_photo.max' => 'Choose a JPEG, PNG, or WebP photo up to 5 MiB. Your saved draft has not changed.',
            'content.introduction_message.not_regex' => 'Use plain text without HTML or angle brackets.',
            'content.closing_message.not_regex' => 'Use plain text without HTML or angle brackets.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'content.introduction_message' => 'introduction message',
            'hero_photo' => 'hero photo',
            'content.closing_message' => 'closing message',
            'content.sections.ceremony' => 'ceremony visibility',
            'content.sections.reception' => 'reception visibility',
            'content.sections.closing' => 'closing visibility',
        ];
    }
}
