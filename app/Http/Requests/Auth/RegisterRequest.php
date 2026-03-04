<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'cnic' => [
                'required',
                'string',
                'max:30',
                'unique:users,cnic',
                Rule::when($this->input('nationality', 'pakistani') === 'pakistani', [
                    'regex:/^\d{5}-\d{7}-\d{1}$/',
                ]),
            ],
            'mobile' => ['required', 'string', 'regex:/^(\+92|0)?3[0-9]{9}$/', 'max:20'],
            'nationality' => ['required', 'string', 'in:pakistani,foreign_national'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnic.regex' => 'CNIC must be in the format XXXXX-XXXXXXX-X.',
            'cnic.unique' => 'This CNIC is already registered.',
            'email.unique' => 'This email is already registered.',
            'mobile.regex' => 'Enter a valid Pakistani mobile number (e.g. 03001234567).',
        ];
    }
}
