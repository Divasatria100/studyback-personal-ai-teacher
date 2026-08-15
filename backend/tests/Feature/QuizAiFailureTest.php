<?php

namespace Tests\Feature;

use App\Models\Chunk;
use App\Models\Material;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use App\Services\Ai\Contracts\AiServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quiz generation + answer evaluation AI failure semantics (AI Architecture
 * §7, §8, §11.5): a structured-output failure must never persist a partial quiz
 * or a corrupt Learning State — the transaction is never opened on AI failure.
 */
class QuizAiFailureTest extends TestCase
{
    use RefreshDatabase;

    private function seedStudySession(User $user): array
    {
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);
        $subtopic = Subtopic::factory()->create(['topic_id' => $topic->id, 'order_index' => 0]);
        Chunk::factory()->create([
            'material_id' => $material->id,
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
            'chunk_index' => 0,
        ]);

        $session = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'quiz_me',
            'difficulty' => 'medium',
            'topic_ids' => [$topic->id],
        ])->json();

        return [$material, $topic, $subtopic, $session];
    }

    /**
     * Rebind the ai_service singleton so the Mock AI Provider reads the new
     * override config on its next resolution (the provider is cached otherwise).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function overrideMockProvider(array $overrides): void
    {
        config()->set('ai.providers.mock', $overrides + (array) config('ai.providers.mock'));
        app()->forgetInstance(AiServiceInterface::class);
    }

    public function test_invalid_quiz_output_returns_503_and_persists_nothing(): void
    {
        $this->overrideMockProvider(['override_questions' => '{broken json']);

        $user = User::factory()->create();
        [$material, $topic, , $session] = $this->seedStudySession($user);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$session['id']}/quizzes", [
                'topic_id' => $topic->id,
                'question_count' => 3,
            ])
            ->assertStatus(503);

        $this->assertDatabaseCount('quizzes', 0);
        $this->assertDatabaseCount('quiz_questions', 0);
    }

    public function test_quiz_output_with_foreign_subtopic_id_is_rejected_without_persistence(): void
    {
        $user = User::factory()->create();
        [$material, $topic, , $session] = $this->seedStudySession($user);

        $foreignSubtopic = Subtopic::factory()->create(['topic_id' => Topic::factory()->create([
            'material_id' => Material::factory()->create(['user_id' => $user->id])->id,
        ])->id]);

        $this->overrideMockProvider(['override_questions' => json_encode(array_fill(0, 3, [
            'question_type' => 'multiple_choice',
            'question_text' => 'Foreign question?',
            'options' => ['A', 'B'],
            'correct_answer' => 'A',
            'subtopic_id' => $foreignSubtopic->id,
        ]))]);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$session['id']}/quizzes", [
                'topic_id' => $topic->id,
                'question_count' => 3,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('quizzes', 0);
        $this->assertDatabaseCount('quiz_questions', 0);
    }

    public function test_ai_question_type_validation_rejects_wrong_type_after_retry_exhausted(): void
    {
        $user = User::factory()->create();
        [$material, $topic, $subtopic, $session] = $this->seedStudySession($user);

        $this->overrideMockProvider(['override_questions' => json_encode(array_fill(0, 3, [
            'question_type' => 'essay',
            'question_text' => 'Essay question?',
            'correct_answer' => 'Answer',
            'subtopic_id' => $subtopic->id,
        ]))]);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$session['id']}/quizzes", [
                'topic_id' => $topic->id,
                'question_count' => 3,
            ])
            ->assertStatus(503);

        $this->assertDatabaseCount('quizzes', 0);
    }

    public function test_invalid_evaluation_returns_503_and_preserves_previous_state(): void
    {
        $user = User::factory()->create();
        [$material, $topic, $subtopic, $session] = $this->seedStudySession($user);

        $quiz = $this->actingAs($user)->postJson("/api/study-sessions/{$session['id']}/quizzes", [
            'topic_id' => $topic->id,
            'question_count' => 3,
        ])->json();

        $this->assertDatabaseCount('quizzes', 1);

        $questionId = $quiz['questions'][0]['id'];

        $this->overrideMockProvider(['override_evaluation' => '{failure']);

        $this->actingAs($user)
            ->postJson("/api/quizzes/{$quiz['id']}/questions/{$questionId}/answer", [
                'submitted_answer' => 'Any answer',
            ])
            ->assertStatus(503);

        $this->assertDatabaseCount('quiz_answers', 0);

        $subtopic->refresh();
        $this->assertSame(0.0, (float) $subtopic->mastery_score);
        $this->assertSame('not_started', $subtopic->status);
    }

    public function test_successful_answer_still_persists_within_normal_flow(): void
    {
        $user = User::factory()->create();
        [$material, $topic, , $session] = $this->seedStudySession($user);

        $quiz = $this->actingAs($user)->postJson("/api/study-sessions/{$session['id']}/quizzes", [
            'topic_id' => $topic->id,
            'question_count' => 3,
        ])->json();

        $questionId = $quiz['questions'][0]['id'];

        $this->actingAs($user)
            ->postJson("/api/quizzes/{$quiz['id']}/questions/{$questionId}/answer", [
                'submitted_answer' => 'Yes',
            ])
            ->assertOk();

        $this->assertDatabaseCount('quiz_answers', 1);
    }
}
