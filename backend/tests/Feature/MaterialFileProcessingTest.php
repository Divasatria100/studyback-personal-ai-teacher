<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use App\Services\Processing\Exceptions\PdfExtractionException;
use App\Services\Processing\PdfTextExtractor;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * File upload / validation / storage / download for the Materials module
 * (API Design §8, Tech Stack §4–5). Covers private local storage, safe stored
 * path, storage failures, and ownership-scoped download.
 */
class MaterialFileProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function mockExtractor(string $text): void
    {
        $mock = $this->createMock(PdfTextExtractor::class);
        $mock->method('extract')->willReturn($text);

        app()->instance(PdfTextExtractor::class, $mock);
    }

    private function mockExtractorFailure(): void
    {
        $mock = $this->createMock(PdfTextExtractor::class);
        $mock->method('extract')->willThrowException(new PdfExtractionException('Corrupted PDF.'));

        app()->instance(PdfTextExtractor::class, $mock);
    }

    public function test_upload_requires_authentication(): void
    {
        $this->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])->assertStatus(401);
    }

    public function test_upload_requires_a_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/materials', [
            'title' => 'Missing file material',
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_empty_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_file_is_stored_under_private_local_disk_with_safe_name(): void
    {
        Storage::fake('local');
        $this->mockExtractor('Object oriented programming notes.');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('broken name.pdf', 100, 'application/pdf'),
            'title' => 'Safe storage',
        ]);

        $response->assertStatus(201)->assertJsonPath('status', 'ready');

        $material = Material::findOrFail($response->json('id'));

        $this->assertStringStartsWith('materials/', $material->file_path);
        $this->assertNotSame('broken name.pdf', $material->file_path);
        $this->assertSame('broken name.pdf', $material->original_filename);
        $this->assertTrue(Storage::disk('local')->exists($material->file_path));
    }

    public function test_original_filename_is_kept_separate_from_stored_path(): void
    {
        Storage::fake('local');
        $this->mockExtractor('Lecture content about polymorphism.');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('my-notes.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(201);

        $material = Material::findOrFail($response->json('id'));

        $this->assertSame('my-notes.pdf', $material->original_filename);
        $this->assertNotSame('my-notes.pdf', $material->file_path);
    }

    public function test_storage_failure_returns_clean_error_and_no_material(): void
    {
        $this->mockExtractor('Extraction never reached when storage fails.');

        $failingAdapter = $this->createMock(FilesystemAdapter::class);
        $failingAdapter->method('putFileAs')->willReturn(false);

        app('filesystem')->set('local', $failingAdapter);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('message', 'The uploaded file could not be stored. Please try again.');

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_owner_can_download_material(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('materials/downloadable.pdf', '%PDF-1.4 test payload');

        $user = User::factory()->create();
        $material = Material::factory()->create([
            'user_id' => $user->id,
            'original_filename' => 'downloadable.pdf',
            'file_path' => 'materials/downloadable.pdf',
        ]);

        $this->actingAs($user)
            ->getJson("/api/materials/{$material->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=downloadable.pdf');
    }

    public function test_download_requires_authentication(): void
    {
        $user = User::factory()->create();
        $material = Material::factory()->create(['user_id' => $user->id]);

        $this->getJson("/api/materials/{$material->id}/download")->assertStatus(401);
    }

    public function test_non_owner_cannot_download_material(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $material = Material::factory()->create([
            'user_id' => $owner->id,
            'file_path' => 'materials/secret.pdf',
        ]);

        $this->actingAs($intruder)
            ->getJson("/api/materials/{$material->id}/download")
            ->assertStatus(404);
    }

    public function test_corrupted_pdf_is_never_marked_ready(): void
    {
        Storage::fake('local');
        $this->mockExtractorFailure();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/materials', [
            'file' => UploadedFile::fake()->create('corrupt.pdf', 100, 'application/pdf'),
            'title' => 'Corrupt PDF',
        ]);

        $response->assertStatus(422)->assertJsonPath('status', 'failed');

        $material = Material::findOrFail($response->json('id'));
        $this->assertSame('failed', $material->status);
        $this->assertSame('Corrupted PDF.', $material->failed_reason);
        $this->assertDatabaseCount('topics', 0);
        $this->assertDatabaseCount('chunks', 0);
    }
}
