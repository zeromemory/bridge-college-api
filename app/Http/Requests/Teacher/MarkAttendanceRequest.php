<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role gate handled by middleware on the route.
        // Class access verified in service layer.
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:users,id'],
            'records.*.status' => ['required', 'in:present,absent,late,leave'],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Cannot mark attendance for a future date.',
            'records.*.status.in' => 'Status must be one of: present, absent, late, leave.',
            'records.*.remarks.max' => 'Remarks must not exceed 500 characters.',
        ];
    }
}
