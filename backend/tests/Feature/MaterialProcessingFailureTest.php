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
 * Topic/subtopic identification pipeline (AI Architecture §5, §13): success
 * persists topics/subtopics/chunks in one transaction; any failure marks the
 * material 'failed' with a reason and never leaves partial data behind.
 */
class MaterialProcessingFailureTest extends TestCase
{
    use RefreshDatabase;

    private function mockExtractor(string $text): void
    {
        $mock = $this->createMock(PdfTextExtractor::class);
        $mock->method('extract')->willReturn($text);

        app()->instance(PdfTextExtractor::class, $mock);
    }

    private function uploadAs(User $user): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            'title' => 'AI Pipeline Test',
        ]);
    }

    public function test_upload_persists_topics_subtopics_and_chunks_for_ready_material(): void
    {
        $user = User::factory()->create();
        $this->mockExtractor('Object oriented programming. Classes, inheritance and polymorphism.');

        $response = $this->uploadAs($user);

        $response->assertStatus(201)->assertJsonPath('status', 'ready');

        $material = Material::findOrFail($response->json('id'));
        $this->assertSame('ready', $material->status);
        $this->assertGreaterThan(0, Topic::where('material_id', $material->id)->count());
        $this->assertGreaterThan(0, $material->topics()->withCount('subtopics')->get()->sum('subtopics_count'));

        $chunkCount = $material->chunks()->count();
        $this->assertGreaterThan(0, $chunkCount);
        $this->assertSame($chunkCount, $material->chunks()->whereNotNull('chunk_index')->count());
    }

    public function test_malformed_ai_topic_output_marks_material_failed_and_returns_422(): void
    {
        config()->set('ai.providers.mock.override_topics', '{definitely not json');

        $user = User::factory()->create();
        $this->mockExtractor('Some study material worth processing.');

        $response = $this->uploadAs($user);

        $response->assertStatus(422)->assertJsonPath('status', 'failed');

        $material = Material::findOrFail($response->json('id'));
        $this->assertSame('failed', $material->status);
        $this->assertStringContainsString('invalid topic structure', $material->failed_reason);
        $this->assertDatabaseCount('topics', 0);
        $this->assertDatabaseCount('subtopics', 0);
        $this->assertDatabaseCount('chunks', 0);
    }

    public function test_invalid_ai_topic_shape_marks_material_failed(): void
    {
        config()->set('ai.providers.mock.override_topics', json_encode([
            ['name' => 'Topic without subtopics key'],
        ]));

        $user = User::factory()->create();
        $this->mockExtractor('Valid text that still fails shape validation.');

        $response = $this->uploadAs($user);

        $response->assertStatus(422)->assertJsonPath('status', 'failed');
        $this->assertDatabaseCount('topics', 0);
        $this->assertDatabaseCount('subtopics', 0);
    }

    public function test_ai_provider_unreachable_returns_503_and_marks_material_failed(): void
    {
        config()->set('ai.providers.mock.failure', true);

        $user = User::factory()->create();
        $this->mockExtractor('Material that triggers a provider failure.');

        $response = $this->uploadAs($user);

        $response->assertStatus(503)->assertJsonPath('material.status', 'failed');

        $material = Material::findOrFail($response->json('material.id'));
        $this->assertSame('failed', $material->status);
        $this->assertStringContainsString('AI provider unavailable', $material->failed_reason);
        $this->assertDatabaseCount('topics', 0);
        $this->assertDatabaseCount('chunks', 0);
    }

    public function test_empty_extraction_marks_material_failed_and_returns_422(): void
    {
        $user = User::factory()->create();
        $this->mockExtractor('   ');

        $response = $this->uploadAs($user);

        $response->assertStatus(422)->assertJsonPath('status', 'failed');
        $this->assertDatabaseCount('chunks', 0);
        $this->assertDatabaseCount('topics', 0);
        $this->assertDatabaseCount('subtopics', 0);
    }

    public function test_extraction_via_spatie_binary_path_is_config_driven(): void
    {
        $this->assertIsString(config('processing.pdftotext_binary'));
    }
}
