<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEventLocationInput;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    use ValidatesEventLocationInput;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && (int) getLogInUserId() === (int) $event->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $event = $this->route('event');

        return array_merge([
            'name' => [
                'required',
                Rule::unique('events')
                    ->ignore($event->id)
                    ->where('user_id', getLogInUserId()),
            ],
            'event_link' => 'required|regex:/^[A-Za-z0-9\-]+$/|unique:events,event_link,'.$event->id,
            'event_color' => 'required',
            'event_type' => ['required', Rule::in(array_keys(Event::EVENT_TYPE))],
            'payable_amount' => 'nullable|numeric|gt:0',
            'description' => 'nullable|string|max:5000',
        ], $this->eventLocationRules());
    }
}
