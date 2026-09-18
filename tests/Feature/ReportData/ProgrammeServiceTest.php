<?php

namespace Tests\Feature\ReportData;

use App\Models\Project;
use App\Models\ProjectProgrammeVersion;
use App\Models\User;
use App\Services\ReportData\ProgrammeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgrammeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProgrammeService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProgrammeService;
        $this->user = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x'), 'status' => 'active']);
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    /** @return array<int, array<string, mixed>> */
    private function activities(int $n = 3): array
    {
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            $rows[] = [
                'name' => "Activity {$i}",
                'outline_level' => 1,
                'duration_days' => 5,
                'start' => '2026-01-01',
                'finish' => '2026-01-05',
                'actual_pct' => 50.0,
                'plan_pct' => 60.0,
                'is_summary' => false,
            ];
        }

        return $rows;
    }

    public function test_create_version_marks_current_and_clears_siblings(): void
    {
        $project = $this->project();

        $v1 = $this->service->createVersion($project->id, [
            'label' => 'V1', 'status_date' => '2026-01-15', 'source_type' => 'xlsx',
        ], $this->activities(), $this->user->id);

        $this->assertTrue($v1->fresh()->is_current);
        $this->assertEquals(3, $v1->activity_count);
        $this->assertEquals(3, $v1->activities()->count());

        $v2 = $this->service->createVersion($project->id, [
            'label' => 'V2', 'status_date' => '2026-02-15', 'source_type' => 'xlsx',
        ], $this->activities(2), $this->user->id);

        $this->assertTrue($v2->fresh()->is_current);
        $this->assertFalse($v1->fresh()->is_current);
    }

    public function test_create_version_with_set_current_false_leaves_existing_current(): void
    {
        $project = $this->project();

        $v1 = $this->service->createVersion($project->id, [
            'label' => 'V1', 'status_date' => '2026-01-15', 'source_type' => 'xlsx',
        ], $this->activities(), $this->user->id);

        $this->assertTrue($v1->fresh()->is_current);

        $v2 = $this->service->createVersion($project->id, [
            'label' => 'V2', 'status_date' => '2026-02-15', 'source_type' => 'xlsx', 'set_current' => false,
        ], $this->activities(), $this->user->id);

        $this->assertFalse($v2->fresh()->is_current);
        $this->assertTrue($v1->fresh()->is_current);
    }

    public function test_delete_current_promotes_latest_remaining(): void
    {
        $project = $this->project();

        $v1 = $this->service->createVersion($project->id, [
            'label' => 'V1', 'status_date' => '2026-01-15', 'source_type' => 'xlsx',
        ], $this->activities(), $this->user->id);

        $v2 = $this->service->createVersion($project->id, [
            'label' => 'V2', 'status_date' => '2026-02-15', 'source_type' => 'xlsx',
        ], $this->activities(), $this->user->id);

        $this->assertTrue($v2->fresh()->is_current);

        $this->service->delete($v2);

        $this->assertNull(ProjectProgrammeVersion::find($v2->id));
        $this->assertTrue($v1->fresh()->is_current);
    }

    public function test_create_version_with_more_than_2000_activities_throws_validation_exception(): void
    {
        $project = $this->project();

        $this->expectException(ValidationException::class);

        $this->service->createVersion($project->id, [
            'label' => 'Big', 'source_type' => 'xlsx',
        ], $this->activities(2001), $this->user->id);
    }

    public function test_create_version_with_zero_activities_throws_validation_exception(): void
    {
        $project = $this->project();

        $this->expectException(ValidationException::class);

        $this->service->createVersion($project->id, [
            'label' => 'Empty', 'source_type' => 'xlsx',
        ], [], $this->user->id);
    }

    public function test_current_for_falls_back_to_latest_when_none_flagged(): void
    {
        $project = $this->project();

        $v1 = $this->service->createVersion($project->id, [
            'label' => 'V1', 'status_date' => '2026-01-15', 'source_type' => 'xlsx', 'set_current' => false,
        ], $this->activities(), $this->user->id);

        $v2 = $this->service->createVersion($project->id, [
            'label' => 'V2', 'status_date' => '2026-02-15', 'source_type' => 'xlsx', 'set_current' => false,
        ], $this->activities(), $this->user->id);

        $this->assertFalse($v1->fresh()->is_current);
        $this->assertFalse($v2->fresh()->is_current);

        $current = $this->service->currentFor($project->id);

        $this->assertNotNull($current);
        $this->assertEquals($v2->id, $current->id);
    }
}
