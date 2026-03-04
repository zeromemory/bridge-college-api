<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonalDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'father_name' => ['required', 'string', 'max:255'],
            'father_cnic' => ['required', 'string', 'regex:/^\d{5}-\d{7}-\d$/'],
            'father_phone' => ['required', 'string', 'max:20'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],
            'guardian_income' => ['nullable', 'string', 'max:50'],
            'gender' => ['required', 'in:male,female,transgender'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'nationality' => ['required', 'string', 'max:50'],
            'religion' => ['required', 'string', 'max:50'],
            'mother_tongue' => ['nullable', 'string', 'max:50'],
            'postal_address' => ['required', 'string', 'max:500'],
            'permanent_address' => ['required', 'string', 'max:500'],
            'same_address' => ['required', 'boolean'],
            'cnic_issuance_date' => ['nullable', 'date', 'before_or_equal:today'],
            'phone_landline' => ['nullable', 'string', 'max:20'],
            'education' => ['required', 'array', 'min:1'],
            'education.*.qualification' => ['required', 'string', 'max:100'],
            'education.*.board_university' => ['required', 'string', 'max:255'],
            'education.*.roll_no' => ['nullable', 'string', 'max:50'],
            'education.*.registration_no' => ['nullable', 'string', 'max:50'],
            'education.*.exam_type' => ['nullable', 'string', 'max:50'],
            'education.*.exam_year' => ['required', 'integer', 'min:1990', 'max:' . date('Y')],
            'education.*.total_marks' => ['required', 'integer', 'min:1'],
            'education.*.obtained_marks' => ['required', 'integer', 'min:0'],
        ];
    }
}
