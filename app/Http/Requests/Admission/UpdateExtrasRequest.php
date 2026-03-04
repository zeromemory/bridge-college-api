<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExtrasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'study_from' => ['nullable', 'in:within_pakistan,overseas'],
            'prior_computer_knowledge' => ['required', 'boolean'],
            'has_computer' => ['required', 'boolean'],
            'internet_type' => ['nullable', 'in:dsl,cable,3g4g,fiber,none'],
            'heard_about_us' => ['nullable', 'string', 'max:255'],
            'scholarship_interest' => ['required', 'boolean'],
        ];
    }
}
