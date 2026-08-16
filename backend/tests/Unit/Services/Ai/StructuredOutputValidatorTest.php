<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\Exceptions\InvalidStructuredOutputException;
use App\Services\Ai\StructuredOutputValidator;
use Tests\TestCase;

/**
 * Structural (shape) validation of AI output (AI Architecture §10). These rules
 * run inside ai_service, independent of the producing provider/model.
 * Business-rule validation (subtopic ownership, question count, etc.) lives in
 * the application modules, not here.
 */
class StructuredOutputValidatorTest extends TestCase
{
    private StructuredOutputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new StructuredOutputValidator;
    }

    // ---------- topics ----------

    public function test_valid_topics_pass(): void
    {
        $topics = $this->validator->topics([
            [
                'name' => 'Inheritance',
                'description' => 'How classes derive behavior.',
                'subtopics' => [
                    ['name' => 'Polymorphism', 'description' => 'One interface, many forms.'],
                ],
            ],
        ]);

        $this->assertSame('Inheritance', $topics[0]['name']);
        $this->assertSame('Polymorphism', $topics[0]['subtopics'][0]['name']);
    }

    public function test_topics_without_description_are_normalized_to_empty(): void
    {
        $topics = $this->validator->topics([
            ['name' => 'Only Name', 'subtopics' => [['name' => 'Sub']]],
        ]);

        $this->assertNull($topics[0]['description']);
        $this->assertNull($topics[0]['subtopics'][0]['description']);
    }

    public function test_empty_topics_array_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->topics([]);
    }

    public function test_associative_topics_array_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->topics(['name' => 'Not a list']);
    }

    public function test_topic_without_name_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->topics([
            ['description' => 'no name', 'subtopics' => []],
        ]);
    }

    public function test_topic_with_blank_name_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->topics([
            ['name' => '   ', 'subtopics' => []],
        ]);
    }

    public function test_topic_without_subtopics_array_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->topics([
            ['name' => 'Missing subtopics key'],
        ]);
    }

    public function test_subtopic_without_name_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->topics([
            ['name' => 'T', 'subtopics' => [['description' => 'no name']]],
        ]);
    }

    public function test_topic_with_empty_subtopics_array_is_allowed(): void
    {
        $topics = $this->validator->topics([
            ['name' => 'Introduction to Cell Biology', 'subtopics' => []],
        ]);

        $this->assertSame('Introduction to Cell Biology', $topics[0]['name']);
        $this->assertSame([], $topics[0]['subtopics']);
    }

    public function test_material_with_zero_total_subtopics_is_allowed(): void
    {
        $topics = $this->validator->topics([
            ['name' => 'Topic A', 'subtopics' => []],
            ['name' => 'Topic B', 'subtopics' => []],
        ]);

        $this->assertCount(2, $topics);
        $this->assertSame([], $topics[0]['subtopics']);
        $this->assertSame([], $topics[1]['subtopics']);
    }

    public function test_mixed_topics_with_and_without_subtopics_are_allowed(): void
    {
        $topics = $this->validator->topics([
            [
                'name' => 'Respiratory System Functions',
                'subtopics' => [
                    ['name' => 'Gas Exchange'],
                    ['name' => 'Acid-Base Balance'],
                ],
            ],
            ['name' => 'Introduction to Cell Biology', 'subtopics' => []],
        ]);

        $this->assertCount(2, $topics);
        $this->assertCount(2, $topics[0]['subtopics']);
        $this->assertSame([], $topics[1]['subtopics']);
    }

    // ---------- questions ----------

    public function test_valid_multiple_choice_question_passes(): void
    {
        $questions = $this->validator->questions([
            [
                'question_type' => 'multiple_choice',
                'question_text' => 'Which statement is correct?',
                'options' => ['A', 'B', 'C'],
                'correct_answer' => 'A',
                'subtopic_id' => 42,
            ],
        ]);

        $this->assertSame('multiple_choice', $questions[0]['question_type']);
        $this->assertSame(['A', 'B', 'C'], $questions[0]['options']);
        $this->assertSame(42, $questions[0]['subtopic_id']);
    }

    public function test_valid_true_false_question_allows_null_options(): void
    {
        $questions = $this->validator->questions([
            [
                'question_type' => 'true_false',
                'question_text' => 'Water boils at 100 C.',
                'options' => null,
                'correct_answer' => 'True',
                'subtopic_id' => 1,
            ],
        ]);

        $this->assertNull($questions[0]['options']);
    }

    public function test_short_answer_question_with_no_options_passes(): void
    {
        $questions = $this->validator->questions([
            [
                'question_type' => 'short_answer',
                'question_text' => 'Define encapsulation.',
                'correct_answer' => 'Bundling data and methods.',
                'subtopic_id' => 7,
            ],
        ]);

        $this->assertNull($questions[0]['options']);
    }

    public function test_empty_questions_array_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->questions([]);
    }

    public function test_unsupported_question_type_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->questions([
            [
                'question_type' => 'essay',
                'question_text' => 'text',
                'correct_answer' => 'answer',
                'subtopic_id' => 1,
            ],
        ]);
    }

    public function test_multiple_choice_with_single_option_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->questions([
            [
                'question_type' => 'multiple_choice',
                'question_text' => 'text',
                'options' => ['only one'],
                'correct_answer' => 'only one',
                'subtopic_id' => 1,
            ],
        ]);
    }

    public function test_question_without_text_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->questions([
            [
                'question_type' => 'multiple_choice',
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
                'subtopic_id' => 1,
            ],
        ]);
    }

    public function test_question_without_correct_answer_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->questions([
            [
                'question_type' => 'multiple_choice',
                'question_text' => 'text',
                'options' => ['A', 'B'],
                'subtopic_id' => 1,
            ],
        ]);
    }

    public function test_question_with_non_numeric_subtopic_id_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->questions([
            [
                'question_type' => 'multiple_choice',
                'question_text' => 'text',
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
                'subtopic_id' => 'not-a-number',
            ],
        ]);
    }

    public function test_question_without_subtopic_id_passes_in_topic_only_mode(): void
    {
        $questions = $this->validator->questions([
            [
                'question_type' => 'multiple_choice',
                'question_text' => 'text',
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
            ],
        ], false);

        $this->assertNull($questions[0]['subtopic_id']);
    }

    public function test_question_subtopic_id_is_nulled_in_topic_only_mode(): void
    {
        $questions = $this->validator->questions([
            [
                'question_type' => 'multiple_choice',
                'question_text' => 'text',
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
                'subtopic_id' => 42,
            ],
        ], false);

        $this->assertNull($questions[0]['subtopic_id']);
    }

    // ---------- evaluation ----------

    public function test_valid_evaluation_passes(): void
    {
        $verdict = $this->validator->evaluation([
            'is_correct' => true,
            'feedback' => 'Great job!',
        ]);

        $this->assertTrue($verdict['is_correct']);
        $this->assertSame('Great job!', $verdict['feedback']);
    }

    public function test_evaluation_with_boolean_string_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->evaluation(['is_correct' => 'true', 'feedback' => 'feedback']);
    }

    public function test_evaluation_missing_is_correct_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->evaluation(['feedback' => 'feedback']);
    }

    public function test_evaluation_with_empty_feedback_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->evaluation(['is_correct' => false, 'feedback' => '   ']);
    }

    public function test_evaluation_missing_feedback_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->evaluation(['is_correct' => false]);
    }

    public function test_evaluation_non_object_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->evaluation(['just a list']);
    }

    public function test_evaluation_null_is_rejected(): void
    {
        $this->expectException(InvalidStructuredOutputException::class);

        $this->validator->evaluation(null);
    }
}
