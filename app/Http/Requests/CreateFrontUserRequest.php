<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFrontUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|regex:/(^([a-zA-z]+)(\d+)?$)/u',
            'last_name' => 'required|string|regex:/(^([a-zA-z]+)(\d+)?$)/u',
            'email' => 'required|email:filter|unique:users,email',
            'password' => 'required|same:password_confirmation|min:6',
            'phone_number' => 'required|string|regex:/^[0-9]{10}$/|unique:users,phone_number',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            'phone_number.required' => 'Phone number is required.',
            'phone_number.regex' => 'Phone number must be exactly 10 digits.',
            'phone_number.unique' => 'This phone number is already registered.',
        ];
    }
}
