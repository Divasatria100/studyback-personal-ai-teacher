<?php

namespace App\Http\Requests\StudySessions;

use Illuminate\Foundation\Http\FormRequest;

class StoreExplanationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subtopic_id' => ['required', 'integer'],
            'intent' => ['required', 'string', 'in:explain,simplify,example,review'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
