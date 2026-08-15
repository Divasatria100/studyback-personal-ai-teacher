<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use App\Services\Processing\PdfTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MaterialApiTest extends TestCase
{
    use RefreshDatabase;

    private function mockExtractor(string $text): void
    {
        $mock = $this->createMock(PdfTextExtractor::class);
        $mock->method('extract')->willReturn($text);

        app()->instance(PdfTextExtractor::class, $mock);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/materials')->assertStatus(401);
    }

    public function test_upload_processes_and_returns_ready_material(): void
    {
        $user = User::factory()->create();
        $this->mockExtractor('Object oriented programming. Classes, inheritance and polymorphism.');

        $response = $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('oop-notes.pdf', 100, 'application/pdf'),
            'title' => 'Object Oriented Programming',
            'description' => 'Lecture notes',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('title', 'Object Oriented Programming')
            ->assertJsonStructure(['id', 'status', 'topics_count', 'overall_mastery', 'created_at']);

        $id = $response->json('id');

        $this->assertDatabaseHas('materials', ['id' => $id, 'status' => 'ready']);
        $this->assertGreaterThan(0, Topic::where('material_id', $id)->count());
        $this->assertGreaterThan(0, Subtopic::whereIn('topic_id', Topic::where('material_id', $id)->pluck('id'))->count());
    }

    public function test_upload_rejects_non_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('notes.txt', 100, 'text/plain'),
        ])->assertStatus(422);
    }

    public function test_list_is_scoped_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Material::factory()->count(2)->create(['user_id' => $user->id]);
        Material::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/materials');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta']);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEmpty(Material::whereNotIn('id', $ids)->where('user_id', $user->id)->get()->where('id', Material::where('user_id', $other->id)->value('id'))->all());
    }

    public function test_show_returns_404_for_foreign_material(): void
    {
        $other = User::factory()->create();
        $foreignMaterial = Material::factory()->create(['user_id' => $other->id]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/materials/{$foreignMaterial->id}")
            ->assertStatus(404);
    }

    public function test_topics_tree_returns_mastery_and_status(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['user_id' => $user->id]);

        $topic = Topic::factory()->create(['material_id' => $material->id, 'order_index' => 0]);
        $subtopic = Subtopic::factory()->create([
            'topic_id' => $topic->id,
            'name' => 'Polymorphism',
            'mastery_score' => 42,
            'status' => 'needs_review',
        ]);

        $this->actingAs($user)
            ->getJson("/api/materials/{$material->id}/topics")
            ->assertOk()
            ->assertJsonPath('material_id', $material->id)
            ->assertJsonCount(1, 'topics')
            ->assertJsonPath('topics.0.subtopics.0.mastery_score', 42)
            ->assertJsonPath('topics.0.subtopics.0.status', 'needs_review');
    }
}
