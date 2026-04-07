<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role gate handled by `teacher` middleware on the route. Subject
        // ownership is verified inside ClassMaterialService::create().
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:file,link'],

            'file' => [
                'required_if:type,file',
                'prohibited_if:type,link',
                'file',
                'max:10240', // 10MB in KB
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/png,image/jpeg',
            ],
            'external_url' => [
                'required_if:type,link',
                'prohibited_if:type,file',
                'url',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'File must not exceed 10MB.',
            'file.mimetypes' => 'Allowed file types: PDF, Word, PowerPoint, PNG, JPEG.',
            'external_url.url' => 'Please provide a valid URL (including https://).',
        ];
    }
}
