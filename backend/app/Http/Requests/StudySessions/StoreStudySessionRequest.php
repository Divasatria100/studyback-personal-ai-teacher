<?php

namespace App\Http\Requests\StudySessions;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudySessionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $material = $this->route('material');

        return [
            'mode' => ['required', 'string', 'in:teach_me,quiz_me,review_weak_topics,guided_study_session'],
            'difficulty' => ['nullable', 'string', 'in:easy,medium,hard'],
            'topic_ids' => ['required', 'array', 'min:1'],
            'topic_ids.*' => [
                'integer',
            ],
        ];
    }
}
