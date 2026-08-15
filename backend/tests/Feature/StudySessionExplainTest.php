<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use App\Services\Processing\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teach Me / Explanation capability (AI Architecture §6, API Design §14.2):
 * explanations are grounded in session-material context, subtopics must belong
 * to the session's material (404 otherwise), and the endpoint is read-only
 * (no study-session state is ever mutated).
 */
class StudySessionExplainTest extends TestCase
{
    use RefreshDatabase;

    private function seedMaterialWithChunks(User $user): Material
    {
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);
        $subtopic = Subtopic::factory()->create(['topic_id' => $topic->id, 'order_index' => 0]);

        $this->createMock(PdfTextExtractor::class);

        // Insert a retrievable chunk for the subtopic via the models used by the pipeline.
        $material->chunks()->create([
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
            'content' => 'Polymorphism allows one interface to describe many implementations.',
            'chunk_index' => 0,
        ]);

        return $material;
    }

    private function createSession(User $user, Material $material): int
    {
        $topicIds = $material->topics->pluck('id')->all();

        return $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'teach_me',
            'topic_ids' => $topicIds,
        ])->json('id');
    }

    public function test_explanation_persists_no_state_and_returns_text(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialWithChunks($user);
        $subtopicId = $material->topics->first()->subtopics->first()->id;
        $sessionId = $this->createSession($user, $material);

        $response = $this->actingAs($user)->postJson("/api/study-sessions/{$sessionId}/explanations", [
            'subtopic_id' => $subtopicId,
            'intent' => 'explain',
        ]);

        $response->assertOk()
            ->assertJsonPath('subtopic_id', $subtopicId)
            ->assertJsonStructure(['explanation']);

        $this->assertSame('active', $material->studySessions()->find($sessionId)->status);
    }

    public function test_explanation_with_review_intent_on_weak_subtopic(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialWithChunks($user);
        $subtopic = $material->topics->first()->subtopics->first();
        $subtopic->forceFill(['mastery_score' => 30, 'status' => 'needs_review'])->save();
        $sessionId = $this->createSession($user, $material);

        $response = $this->actingAs($user)->postJson("/api/study-sessions/{$sessionId}/explanations", [
            'subtopic_id' => $subtopic->id,
            'intent' => 'review',
        ]);

        $response->assertOk()->assertJsonStructure(['explanation']);
    }

    public function test_foreign_subtopic_not_in_session_material_returns_404(): void
    {
        $user = User::factory()->create();

        $materialA = $this->seedMaterialWithChunks($user);
        $sessionId = $this->createSession($user, $materialA);

        // A subtopic that belongs to a *different* material owned by the same user.
        $otherMaterial = Material::factory()->create(['user_id' => $user->id]);
        $otherTopic = Topic::factory()->create(['material_id' => $otherMaterial->id]);
        $foreignSubtopic = Subtopic::factory()->create(['topic_id' => $otherTopic->id]);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'subtopic_id' => $foreignSubtopic->id,
                'intent' => 'explain',
            ])
            ->assertStatus(404);
    }

    public function test_subtopic_in_same_material_but_unselected_topic_is_allowed(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialWithChunks($user);
        $sessionId = $this->createSession($user, $material);

        // A second topic of the same material that was NOT selected in this session.
        // AI Architecture §6 scopes validation to the session's *material*, not to the
        // topics selected at session creation.
        $unselectedTopic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 1]);
        $unselectedSubtopic = Subtopic::factory()->create(['topic_id' => $unselectedTopic->id]);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'subtopic_id' => $unselectedSubtopic->id,
                'intent' => 'explain',
            ])
            ->assertOk()
            ->assertJsonStructure(['explanation']);
    }

    public function test_provider_failure_returns_503(): void
    {
        config()->set('ai.providers.mock.failure', true);

        $user = User::factory()->create();
        $material = $this->seedMaterialWithChunks($user);
        $subtopicId = $material->topics->first()->subtopics->first()->id;
        $sessionId = $this->createSession($user, $material);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'subtopic_id' => $subtopicId,
                'intent' => 'explain',
            ])
            ->assertStatus(503);
    }

    public function test_invalid_intent_is_rejected_by_validation(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialWithChunks($user);
        $sessionId = $this->createSession($user, $material);

        $this->actingAs($user)
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'subtopic_id' => 1,
                'intent' => 'brainstorm',
            ])
            ->assertStatus(422);
    }

    public function test_explain_on_foreign_session_returns_404(): void
    {
        $other = User::factory()->create();
        $material = $this->seedMaterialWithChunks($other);
        $subtopicId = $material->topics->first()->subtopics->first()->id;
        $sessionId = $this->createSession($other, $material);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/study-sessions/{$sessionId}/explanations", [
                'subtopic_id' => $subtopicId,
                'intent' => 'explain',
            ])
            ->assertStatus(404);
    }
}
