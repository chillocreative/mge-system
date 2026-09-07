<?php

namespace Tests\Feature\Uploads;

use App\Models\Attachment;
use App\Models\Project;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Shared upload engine (plan §26.1) — the one file store every module reuses.
 */
class FileUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FileUploadService
    {
        return app(FileUploadService::class);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_it_stores_a_file_under_a_randomised_name_keeping_the_original_in_db(): void
    {
        $att = $this->service()->attach(
            UploadedFile::fake()->create('Site Plan.pdf', 40, 'application/pdf'),
            $this->project(),
        );

        $this->assertSame('Site Plan.pdf', $att->original_name);
        // Stored name is randomised — never the original.
        $this->assertStringNotContainsString('Site Plan', $att->stored_path);
        $this->assertStringEndsWith('.pdf', $att->stored_path);
        Storage::disk('local')->assertExists($att->stored_path);
    }

    public function test_it_preserves_a_folder_path(): void
    {
        $att = $this->service()->attach(
            UploadedFile::fake()->create('A-101.pdf', 10),
            $this->project(),
            null,
            ['folder_path' => 'Architectural/Level 1'],
        );

        $this->assertSame('Architectural/Level 1', $att->folder_path);
    }

    public function test_it_strips_path_traversal_from_a_folder_path(): void
    {
        $att = $this->service()->attach(
            UploadedFile::fake()->create('x.pdf', 1),
            $this->project(),
            null,
            ['folder_path' => '../../etc/../Secret/Level 1'],
        );

        // No ".." survives; the result cannot escape upward.
        $this->assertStringNotContainsString('..', (string) $att->folder_path);
        $this->assertSame('etc/Secret/Level 1', $att->folder_path);
    }

    public function test_it_rejects_a_disallowed_extension(): void
    {
        $this->expectException(HttpException::class);
        $this->service()->attach(
            UploadedFile::fake()->create('malware.exe', 1),
            $this->project(),
            null,
            ['allowed_extensions' => ['pdf', 'dwg']],
        );
    }

    public function test_it_rejects_an_oversize_file(): void
    {
        $this->expectException(HttpException::class);
        $this->service()->attach(
            UploadedFile::fake()->create('big.pdf', 6000), // 6 MB
            $this->project(),
            null,
            ['max_size_kb' => 5120],
        );
    }

    public function test_it_skips_a_byte_identical_duplicate_when_asked(): void
    {
        $project = $this->project();
        $file = UploadedFile::fake()->createWithContent('same.pdf', 'identical-bytes');

        $first = $this->service()->attach($file, $project, null, ['skip_duplicates' => true]);
        $again = UploadedFile::fake()->createWithContent('same.pdf', 'identical-bytes');
        $second = $this->service()->attach($again, $project, null, ['skip_duplicates' => true]);

        $this->assertSame($first->id, $second->id, 'A duplicate re-upload should return the existing attachment.');
        $this->assertSame(1, Attachment::count());
    }

    public function test_remove_deletes_both_the_row_and_the_file(): void
    {
        $att = $this->service()->attach(UploadedFile::fake()->create('x.pdf', 2), $this->project());
        $path = $att->stored_path;

        $this->service()->remove($att);

        $this->assertSame(0, Attachment::count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_the_attachable_relation_resolves_back(): void
    {
        $project = $this->project();
        $att = $this->service()->attach(UploadedFile::fake()->create('x.pdf', 2), $project);

        $this->assertTrue($att->attachable->is($project));
    }
}
