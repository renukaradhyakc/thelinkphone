<?php

namespace App\Http\Requests;

use App\Models\FrontTestimonial;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFrontTestimonialRequest extends FormRequest
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
        $rules = FrontTestimonial::$editRules;

        return $rules;
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'profile.max' => 'Profile size should be less than 2 MB',
        ];
    }
}
