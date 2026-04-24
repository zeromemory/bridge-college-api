<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership / access checked in service layer.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'in:class_test,assignment,monthly_assessment,quarterly_mock_exam,final_exam'],
            'total_marks' => ['sometimes', 'numeric', 'min:1', 'max:999.5'],
            'date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Cannot set an assessment date in the future.',
            'type.in' => 'Type must be one of: class_test, assignment, monthly_assessment, quarterly_mock_exam, final_exam.',
            'total_marks.max' => 'Total marks cannot exceed 999.5.',
        ];
    }
}
