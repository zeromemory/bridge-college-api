<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'in:photo,cnic_front,cnic_back,father_cnic,marks_sheet,equivalence,additional'],
            'file' => ['required', 'file', 'max:5120'], // 5MB max
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file must not exceed 5MB.',
        ];
    }
}
