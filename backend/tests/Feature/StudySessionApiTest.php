<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use App\Services\Processing\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudySessionApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedMaterialFor(User $user): Material
    {
        $material = Material::factory()->create(['user_id' => $user->id]);
        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);
        $topic2 = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 1]);
        Subtopic::factory()->create(['topic_id' => $topic->id, 'order_index' => 0]);
        Subtopic::factory()->create(['topic_id' => $topic2->id, 'order_index' => 0]);

        return $material;
    }

    public function test_creates_study_session_with_topics(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialFor($user);
        $topicIds = $material->topics->pluck('id')->all();

        $response = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'guided_study_session',
            'difficulty' => 'medium',
            'topic_ids' => $topicIds,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('mode', 'guided_study_session')
            ->assertJsonCount(2, 'topic_ids');

        $this->assertDatabaseCount('study_session_topics', 2);
    }

    public function test_creating_session_for_foreign_material_returns_404(): void
    {
        $other = User::factory()->create();
        $material = $this->seedMaterialFor($other);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/materials/{$material->id}/study-sessions", [
                'mode' => 'teach_me',
                'topic_ids' => $material->topics->pluck('id')->all(),
            ])
            ->assertStatus(404);
    }

    public function test_creating_session_rejects_invalid_topic_ids(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialFor($user);

        $foreignTopic = Topic::factory()->create(['material_id' => Material::factory()->create(['user_id' => $user->id])->id]);

        $this->actingAs($user)
            ->postJson("/api/materials/{$material->id}/study-sessions", [
                'mode' => 'teach_me',
                'topic_ids' => [$foreignTopic->id],
            ])
            ->assertStatus(422);
    }

    public function test_show_returns_session_state(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialFor($user);
        $topicIds = $material->topics->pluck('id')->all();

        $sessionId = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'guided_study_session',
            'topic_ids' => $topicIds,
        ])->json('id');

        $this->actingAs($user)
            ->getJson("/api/study-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonCount(2, 'topic_ids');
    }

    public function test_show_returns_404_for_foreign_session(): void
    {
        $otherUser = User::factory()->create();
        $material = $this->seedMaterialFor($otherUser);
        $sessionId = $this->actingAs($otherUser)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'teach_me',
            'topic_ids' => $material->topics->pluck('id')->all(),
        ])->json('id');

        $this->actingAs(User::factory()->create())
            ->getJson("/api/study-sessions/{$sessionId}")
            ->assertStatus(404);
    }

    public function test_complete_marks_session_done(): void
    {
        $user = User::factory()->create();
        $material = $this->seedMaterialFor($user);
        $sessionId = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'teach_me',
            'topic_ids' => $material->topics->pluck('id')->all(),
        ])->json('id');

        $this->actingAs($user)
            ->patchJson("/api/study-sessions/{$sessionId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure(['ended_at']);

        $this->actingAs($user)
            ->patchJson("/api/study-sessions/{$sessionId}/complete")
            ->assertStatus(409);
    }

    public function test_explanations_generate_text(): void
    {
        $mock = $this->createMock(PdfTextExtractor::class);
        $mock->method('extract')->willReturn('Some study material about polymorphism and inheritance.');
        app()->instance(PdfTextExtractor::class, $mock);

        $user = User::factory()->create();
        $material = $this->seedMaterialFor($user);
        $subtopicId = $material->topics->first()->subtopics->first()->id;

        $sessionId = $this->actingAs($user)->postJson("/api/materials/{$material->id}/study-sessions", [
            'mode' => 'teach_me',
            'topic_ids' => $material->topics->pluck('id')->all(),
        ])->json('id');

        $response = $this->actingAs($user)->postJson("/api/study-sessions/{$sessionId}/explanations", [
            'subtopic_id' => $subtopicId,
            'intent' => 'explain',
        ]);

        $response->assertOk()
            ->assertJsonPath('subtopic_id', $subtopicId)
            ->assertJsonStructure(['explanation']);

        $this->assertNotEmpty($response->json('explanation'));
    }
}
