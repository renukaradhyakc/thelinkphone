<?php

namespace App\Http\Requests;

use App\Models\FrontCMSSetting;
use Illuminate\Foundation\Http\FormRequest;

class CreateFrontCMSSettingRequest extends FormRequest
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
        return FrontCMSSetting::$rules;
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'front_image.max' => 'Image size should be less than 2 MB',
        ];
    }
}
