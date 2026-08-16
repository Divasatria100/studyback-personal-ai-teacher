<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use App\Services\Processing\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Topics without subtopics as first-class learning targets (API Design §10,
 * §11, §12, §14.2): they render in the topic tree, can be explained and
 * quizzed through topic_id, persist chunk context, and carry their own
 * mastery_score/status — without inventing fake subtopics.
 */
class TopicOnlyLearningTargetTest extends TestCase
{
    use RefreshDatabase;

    private function seedTopicOnlyMaterial(User $user): Material
    {
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);

        $material->chunks()->create([
            'topic_id' => $topic->id,
            'subtopic_id' => null,
            'content' => 'Introduction to Cell Biology covers the basic unit of life.',
            'chunk_index' => 0,
        ]);

        return $material;
    }

    private function seedTopicWithSubtopics(User $user): Material
    {
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);
        $subtopic = Subtopic::factory()->create(['topic_id' => $topic->id, 'order_index' => 0]);

        $material->chunks()->create([
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
            'content' => 'Gas Exchange is the primary function of the respiratory system.',
            'chunk_index' => 0,
        ]);

        return $material;
    }

    private function createSession(User $user, Material $material, string $mode = 'teach_me'): int
    {
        $topicIds = $material->topics->pluck('id')->all();

        return $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => $mode,
            'difficulty' => $mode === 'quiz_me' ? 'medium' : null,
            'topic_ids' => $topicIds,
        ])->json('id');
    }

    // ---------- topic-only material processing ----------

    public function test_upload_persists_topic_only_learning_target_and_assigns_chunks(): void
    {
        config()->set('ai.providers.mock.override_topics', json_encode([
            [
                'name' => 'Introduction to Cell Biology',
                'description' => 'Foundational biology concepts.',
                'subtopics' => [],
            ],
            [
                'name' => 'Respiratory System Functions',
                'description' => 'Gas exchange and transport.',
                'subtopics' => [
                    ['name' => 'Gas Exchange', 'description' => 'Alveolar gas exchange.'],
                ],
            ],
        ]));

        $mock = $this->createMock(PdfTextExtractor::class);
        $mock->method('extract')->willReturn('Cells are the basic unit of life. The respiratory system enables gas exchange.');
        app()->instance(PdfTextExtractor::class, $mock);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            'title' => 'Topic-only pipeline',
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'ready');

        $material = Material::findOrFail($response->json('id'));

        $this->assertDatabaseHas('topics', [
            'material_id' => $material->id,
            'name' => 'Introduction to Cell Biology',
        ]);

        $topicOnly = Topic::where('material_id', $material->id)->where('name', 'Introduction to Cell Biology')->first();
        $this->assertSame(0, $topicOnly->subtopics()->count());
        $this->assertGreaterThan(0, $topicOnly->chunks()->whereNull('subtopic_id')->count());
    }

    // ---------- topic tree exposes topic-level mastery ----------

    public function test_topic_tree_exposes_topic_level_mastery_and_status(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicOnlyMaterial($user);
        $topic = $material->topics->first();
        $topic->forceFill(['mastery_score' => 100, 'status' => 'mastered'])->save();

        $response = $this->actingAs($user)
            ->getJson("/api/materials/{$material->id}/topics")
            ->assertOk();

        $this->assertSame($topic->id, $response->json('topics.0.id'));
        $this->assertSame(100, $response->json('topics.0.mastery_score'));
        $this->assertSame('mastered', $response->json('topics.0.status'));
        $this->assertSame([], $response->json('topics.0.subtopics'));
        $this->assertSame(100, $response->json('overall_mastery'));
    }

    // ---------- topic-only explanation ----------

    public function test_topic_only_explanation_returns_topic_scoped_context(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicOnlyMaterial($user);
        $topic = $material->topics->first();
        $sessionId = $this->createSession($user, $material);

        $response = $this->actingAs($user)->postJson("/api/study-sessions/{$sessionId}/explanations", [
            'topic_id' => $topic->id,
            'intent' => 'explain',
        ]);

        $response->assertOk()
            ->assertJsonPath('topic_id', $topic->id)
            ->assertJsonStructure(['explanation']);
    }

    public function test_topic_explanation_is_rejected_when_topic_has_subtopics(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicWithSubtopics($user);
        $topic = $material->topics->first();
        $sessionId = $this->createSession($user, $material);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'topic_id' => $topic->id,
                'intent' => 'explain',
            ])
            ->assertStatus(422);
    }

    public function test_topic_explanation_for_foreign_topic_returns_404(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicOnlyMaterial($user);
        $sessionId = $this->createSession($user, $material);

        $otherMaterial = Material::factory()->create(['user_id' => $user->id]);
        $foreignTopic = Topic::factory()->create(['material_id' => $otherMaterial->id]);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'topic_id' => $foreignTopic->id,
                'intent' => 'explain',
            ])
            ->assertStatus(404);
    }

    public function test_explanation_rejects_both_subtopic_and_topic_ids(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicWithSubtopics($user);
        $topic = $material->topics->first();
        $subtopic = $topic->subtopics->first();
        $sessionId = $this->createSession($user, $material);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'subtopic_id' => $subtopic->id,
                'topic_id' => $topic->id,
                'intent' => 'explain',
            ])
            ->assertStatus(422);
    }

    public function test_explanation_requires_a_learning_target(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicOnlyMaterial($user);
        $sessionId = $this->createSession($user, $material);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'intent' => 'explain',
            ])
            ->assertStatus(422);
    }

    // ---------- topic-only quiz + topic mastery ----------

    public function test_topic_only_quiz_questions_carry_null_subtopic(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicOnlyMaterial($user);
        $topic = $material->topics->first();
        $sessionId = $this->createSession($user, $material, 'quiz_me');

        $response = $this->actingAs($user)->postJson("/api/study-sessions/{$sessionId}/quizzes", [
            'topic_id' => $topic->id,
            'difficulty' => 'medium',
            'question_count' => 3,
        ]);

        $response->assertStatus(201)->assertJsonCount(3, 'questions');

        $this->assertDatabaseHas('quizzes', [
            'id' => $response->json('id'),
            'topic_id' => $topic->id,
            'subtopic_id' => null,
        ]);

        $this->assertSame(
            3,
            \DB::table('quiz_questions')->where('quiz_id', $response->json('id'))->whereNull('subtopic_id')->count()
        );
    }

    public function test_topic_only_answer_updates_topic_mastery_and_returns_topic(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicOnlyMaterial($user);
        $topic = $material->topics->first();
        $sessionId = $this->createSession($user, $material, 'quiz_me');

        $quiz = $this->actingAs($user)->postJson("/api/study-sessions/{$sessionId}/quizzes", [
            'topic_id' => $topic->id,
            'difficulty' => 'medium',
            'question_count' => 3,
        ])->json();

        $questionId = $quiz['questions'][0]['id'];

        $response = $this->actingAs($user)->postJson("/api/quizzes/{$quiz['id']}/questions/{$questionId}/answer", [
            'submitted_answer' => 'Yes',
        ]);

        $response->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonStructure(['ai_feedback', 'topic' => ['id', 'mastery_score', 'status']])
            ->assertJsonPath('topic.id', $topic->id)
            ->assertJsonPath('topic.mastery_score', 100)
            ->assertJsonPath('topic.status', 'mastered');

        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'mastery_score' => 100,
            'status' => 'mastered',
        ]);
    }

    public function test_topic_only_quiz_completion_reports_topic_performance(): void
    {
        $user = User::factory()->create();
        $material = $this->seedTopicOnlyMaterial($user);
        $topic = $material->topics->first();
        $sessionId = $this->createSession($user, $material, 'quiz_me');

        $quiz = $this->actingAs($user)->postJson("/api/study-sessions/{$sessionId}/quizzes", [
            'topic_id' => $topic->id,
            'difficulty' => 'medium',
            'question_count' => 3,
        ])->json();

        foreach ($quiz['questions'] as $question) {
            $this->actingAs($user)->postJson("/api/quizzes/{$quiz['id']}/questions/{$question['id']}/answer", [
                'submitted_answer' => 'Yes',
            ])->assertOk();
        }

        $this->actingAs($user)
            ->getJson("/api/quizzes/{$quiz['id']}")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('topic_performance.0.topic_id', $topic->id)
            ->assertJsonPath('topic_performance.0.topic_name', $topic->name)
            ->assertJsonPath('topic_performance.0.status', 'mastered');

        $this->assertDatabaseHas('quizzes', [
            'id' => $quiz['id'],
            'status' => 'completed',
            'score' => 100,
        ]);
    }
}