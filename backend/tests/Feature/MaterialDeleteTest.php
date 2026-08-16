<?php

namespace Tests\Feature;

use App\Models\Chunk;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\StudySession;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * DELETE /api/materials/{material}: hard-deletes the material row (dependents
 * removed by DB CASCADE) and removes the stored PDF. Ownership is enforced with
 * the same 404-for-foreign-resources convention as the rest of the Materials API.
 */
class MaterialDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_requires_authentication(): void
    {
        $this->deleteJson('/api/materials/1')->assertStatus(401);
    }

    public function test_owner_can_delete_failed_material_and_removes_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('materials/failed.pdf', '%PDF-1.4 failed payload');

        $user = User::factory()->create();
        $material = Material::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'failed_reason' => 'AI provider unavailable: test',
            'file_path' => 'materials/failed.pdf',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/materials/{$material->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
        $this->assertFalse(Storage::disk('local')->exists('materials/failed.pdf'));
    }

    public function test_owner_can_delete_ready_material_and_cascades_dependents(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('materials/ready.pdf', '%PDF-1.4 ready payload');

        $user = User::factory()->create();
        $material = Material::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'file_path' => 'materials/ready.pdf',
        ]);

        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);
        $subtopic = Subtopic::factory()->create(['topic_id' => $topic->id]);
        Chunk::factory()->create([
            'material_id' => $material->id,
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
        ]);

        $session = StudySession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);
        Quiz::factory()->create([
            'study_session_id' => $session->id,
            'topic_id' => $topic->id,
            'subtopic_id' => $subtopic->id,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/materials/{$material->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
        $this->assertDatabaseMissing('subtopics', ['id' => $subtopic->id]);
        $this->assertDatabaseMissing('chunks', ['material_id' => $material->id]);
        $this->assertDatabaseMissing('study_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('quizzes', ['study_session_id' => $session->id]);
        $this->assertFalse(Storage::disk('local')->exists('materials/ready.pdf'));
    }

    public function test_owner_can_delete_processing_material(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $material = Material::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'file_path' => 'materials/inflight.pdf',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/materials/{$material->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    public function test_non_owner_cannot_delete_material(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('materials/foreign.pdf', '%PDF-1.4 secret');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $material = Material::factory()->create([
            'user_id' => $owner->id,
            'file_path' => 'materials/foreign.pdf',
        ]);

        $this->actingAs($intruder)
            ->deleteJson("/api/materials/{$material->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('materials', ['id' => $material->id]);
        $this->assertTrue(Storage::disk('local')->exists('materials/foreign.pdf'));
    }

    public function test_delete_nonexistent_material_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/materials/999999')->assertStatus(404);
    }

    public function test_deleting_material_only_removes_its_own_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $toDelete = Material::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'materials/delete-me.pdf',
        ]);
        $keep = Material::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'materials/keep-me.pdf',
        ]);

        Storage::disk('local')->put('materials/delete-me.pdf', 'A');
        Storage::disk('local')->put('materials/keep-me.pdf', 'B');

        $this->actingAs($user)
            ->deleteJson("/api/materials/{$toDelete->id}")
            ->assertStatus(204);

        $this->assertFalse(Storage::disk('local')->exists('materials/delete-me.pdf'));
        $this->assertTrue(Storage::disk('local')->exists('materials/keep-me.pdf'));
        $this->assertDatabaseHas('materials', ['id' => $keep->id]);
    }
}