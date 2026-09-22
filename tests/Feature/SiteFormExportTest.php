<?php

namespace Tests\Feature;

use App\Models\SiteForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiteFormExportTest extends TestCase
{
    use RefreshDatabase;

    // A valid 1x1 transparent PNG, hard-coded so the test doesn't depend on the GD extension.
    private const TINY_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('projects.view', 'web');
        Permission::findOrCreate('projects.edit', 'web');
    }

    private function actor(): User
    {
        $u = User::create([
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'u-'.uniqid().'@mge-eng.com',
            'password' => bcrypt('x'),
            'status' => 'active',
        ]);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

        return $u;
    }

    private function form(): SiteForm
    {
        return SiteForm::create([
            'form_type' => 'ncr',
            'ref_no' => 'MGE/MGE/NCR/26-01',
            'title' => 'Export test',
        ]);
    }

    public function test_export_docx_returns_word_document(): void
    {
        $actor = $this->actor();
        $form = $this->form();

        $response = $this->actingAs($actor)->postJson("/api/site-forms/{$form->id}/export-docx", [
            'images' => ['data:image/png;base64,'.self::TINY_PNG_BASE64],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('wordprocessingml', $response->headers->get('content-type'));
    }

    public function test_export_docx_requires_at_least_one_image(): void
    {
        $actor = $this->actor();
        $form = $this->form();

        $response = $this->actingAs($actor)->postJson("/api/site-forms/{$form->id}/export-docx", [
            'images' => [],
        ]);

        $response->assertStatus(422);
    }
}
