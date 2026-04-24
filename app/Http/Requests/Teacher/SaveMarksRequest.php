<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class SaveMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access checked in service layer.
        return true;
    }

    public function rules(): array
    {
        return [
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'integer', 'exists:users,id'],
            'marks.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['sometimes', 'boolean'],
            'marks.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'marks.required' => 'At least one student mark entry is required.',
            'marks.*.marks_obtained.min' => 'Marks cannot be negative.',
            'marks.*.remarks.max' => 'Remarks must not exceed 500 characters.',
        ];
    }
}
