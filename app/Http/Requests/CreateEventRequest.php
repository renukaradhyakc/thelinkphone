<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEventLocationInput;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEventRequest extends FormRequest
{
    use ValidatesEventLocationInput;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge([
            'name' => [
                'required',
                Rule::unique('events')
                    ->where('user_id', getLogInUserId()),
            ],
            'event_link' => 'required|regex:/^[A-Za-z0-9\-]+$/|unique:events,event_link',
            'event_color' => 'required',
            'event_type' => ['required', Rule::in(array_keys(Event::EVENT_TYPE))],
            'payable_amount' => 'nullable|numeric|gt:0',
            'description' => 'nullable|string|max:5000',
        ], $this->eventLocationRules());
    }
}
