<?php

namespace App\Http\Requests\StudySessions;

use Illuminate\Foundation\Http\FormRequest;

class StoreExplanationRequest extends FormRequest
{
    /**
     * Exactly one learning target is required: either a subtopic_id (backward
     * compatible) or a topic_id for topic-only topics. The two are mutually
     * exclusive.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subtopic_id' => ['nullable', 'integer', 'required_without:topic_id', 'prohibits:topic_id'],
            'topic_id' => ['nullable', 'integer', 'prohibits:subtopic_id'],
            'intent' => ['required', 'string', 'in:explain,simplify,example,review'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}