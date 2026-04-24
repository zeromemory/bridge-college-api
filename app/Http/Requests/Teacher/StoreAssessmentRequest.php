<?php

namespace App\Http\Requests\Teacher;

use App\Models\ClassSubjectTeacher;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classId = $this->route('class')?->id ?? $this->route('class');
        $subjectId = $this->input('subject_id');

        if (! $classId || ! $subjectId) {
            return true; // Let validation handle missing fields
        }

        return ClassSubjectTeacher::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $this->user()->id)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:class_test,assignment,monthly_assessment,quarterly_mock_exam,final_exam'],
            'total_marks' => ['required', 'numeric', 'min:1', 'max:999.5'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Cannot create an assessment for a future date.',
            'type.in' => 'Type must be one of: class_test, assignment, monthly_assessment, quarterly_mock_exam, final_exam.',
            'total_marks.max' => 'Total marks cannot exceed 999.5.',
        ];
    }
}
