<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class CreateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'program_id' => ['required', 'exists:programs,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'study_mode' => ['required', 'in:at_home,virtual_campus'],
            'city' => ['nullable', 'string', 'max:100'],
        ];
    }
}
