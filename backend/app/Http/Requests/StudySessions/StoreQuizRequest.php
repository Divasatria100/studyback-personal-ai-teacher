<?php

namespace App\Http\Requests\StudySessions;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'topic_id' => ['required', 'integer'],
            'subtopic_id' => ['nullable', 'integer'],
            'difficulty' => ['nullable', 'string', 'in:easy,medium,hard'],
            'question_count' => ['nullable', 'integer', 'min:3', 'max:10'],
        ];
    }
}
