<?php

namespace Tests\Feature;

use App\Models\Chunk;
use App\Models\Material;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedStudySession(User $user, int $topicCount = 1): array
    {
        $material = Material::factory()->create(['user_id' => $user->id]);

        $topics = [];
        for ($i = 0; $i < $topicCount; $i++) {
            $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => $i]);
            $subtopic = Subtopic::factory()->create(['topic_id' => $topic->id, 'order_index' => 0]);
            Chunk::factory()->create([
                'material_id' => $material->id,
                'topic_id' => $topic->id,
                'subtopic_id' => $subtopic->id,
                'chunk_index' => $i,
            ]);
            $topics[] = $topic;
        }

        $session = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'quiz_me',
            'difficulty' => 'medium',
            'topic_ids' => collect($topics)->pluck('id')->all(),
        ])->json();

        return [$material, $topics, $session];
    }

    public function test_generate_quiz_returns_questions_without_correct_answer(): void
    {
        $user = User::factory()->create();
        [$material, $topics, $session] = $this->seedStudySession($user);

        $response = $this->actingAs($user)->postJson("/api/study-sessions/{$session['id']}/quizzes", [
            'topic_id' => $topics[0]->id,
            'difficulty' => 'medium',
            'question_count' => 3,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonCount(3, 'questions');

        $questions = $response->json('questions');
        $this->assertArrayNotHasKey('correct_answer', $questions[0]);
    }

    public function test_generate_quiz_with_insufficient_context_returns_422(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id]);

        $session = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'quiz_me',
            'topic_ids' => [$topic->id],
        ])->json();

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$session['id']}/quizzes", [
                'topic_id' => $topic->id,
                'question_count' => 5,
            ])
            ->assertStatus(422);
    }

    public function test_generate_quiz_for_foreign_session_returns_404(): void
    {
        $other = User::factory()->create();
        [$material, $topics, $session] = $this->seedStudySession($other);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/study-sessions/{$session['id']}/quizzes", [
                'topic_id' => $topics[0]->id,
            ])
            ->assertStatus(404);
    }

    public function test_answer_submission_updates_mastery_and_quiz(): void
    {
        $user = User::factory()->create();
        [$material, $topics, $session] = $this->seedStudySession($user);
        $topic = $topics[0];
        $subtopic = $topic->subtopics->first();

        $quiz = $this->actingAs($user)->postJson("/api/study-sessions/{$session['id']}/quizzes", [
            'topic_id' => $topic->id,
            'question_count' => 3,
        ])->json();

        $questionId = $quiz['questions'][0]['id'];

        $response = $this->actingAs($user)->postJson("/api/quizzes/{$quiz['id']}/questions/{$questionId}/answer", [
            'submitted_answer' => 'Yes',
        ]);

        $response->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonStructure(['ai_feedback', 'subtopic' => ['id', 'mastery_score', 'status']])
            ->assertJsonPath('subtopic.status', 'mastered');

        $this->assertDatabaseCount('quiz_answers', 1);
        $this->assertDatabaseHas('quiz_answers', ['quiz_question_id' => $questionId, 'is_correct' => true]);
    }

    public function test_answer_submission_rejects_duplicate(): void
    {
        $user = User::factory()->create();
        [$material, $topics, $session] = $this->seedStudySession($user);
        $topic = $topics[0];

        $quiz = $this->actingAs($user)->postJson("/api/study-sessions/{$session['id']}/quizzes", [
            'topic_id' => $topic->id,
            'question_count' => 3,
        ])->json();

        $questionId = $quiz['questions'][0]['id'];

        $this->actingAs($user)->postJson("/api/quizzes/{$quiz['id']}/questions/{$questionId}/answer", [
            'submitted_answer' => 'Yes',
        ])->assertOk();

        $this->actingAs($user)->postJson("/api/quizzes/{$quiz['id']}/questions/{$questionId}/answer", [
            'submitted_answer' => 'Yes',
        ])->assertStatus(409);
    }

    public function test_answer_on_foreign_quiz_returns_404(): void
    {
        $other = User::factory()->create();
        [$material, $topics, $session] = $this->seedStudySession($other);
        $topic = $topics[0];

        $quiz = $this->actingAs($other)->postJson("/api/study-sessions/{$session['id']}/quizzes", [
            'topic_id' => $topic->id,
            'question_count' => 3,
        ])->json();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/quizzes/{$quiz['id']}/questions/{$quiz['questions'][0]['id']}/answer", [
                'submitted_answer' => 'Yes',
            ])
            ->assertStatus(404);
    }

    public function test_completing_final_question_scores_quiz(): void
    {
        $user = User::factory()->create();
        [$material, $topics, $session] = $this->seedStudySession($user);
        $topic = $topics[0];

        $quiz = $this->actingAs($user)->postJson("/api/study-sessions/{$session['id']}/quizzes", [
            'topic_id' => $topic->id,
            'question_count' => 3,
        ])->json();

        foreach ($quiz['questions'] as $question) {
            $this->actingAs($user)->postJson("/api/quizzes/{$quiz['id']}/questions/{$question['id']}/answer", [
                'submitted_answer' => 'Yes',
            ])->assertOk();
        }

        $this->assertDatabaseHas('quizzes', [
            'id' => $quiz['id'],
            'status' => 'completed',
            'correct_count' => 3,
            'score' => 100,
        ]);
    }
}
