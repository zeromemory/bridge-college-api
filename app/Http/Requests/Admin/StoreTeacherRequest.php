<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        // No password field — teachers set their own password via the
        // magic-link setup email issued on creation.
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'cnic' => ['required', 'string', 'size:15', 'regex:/^\d{5}-\d{7}-\d{1}$/', 'unique:users,cnic'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ];
    }
}
