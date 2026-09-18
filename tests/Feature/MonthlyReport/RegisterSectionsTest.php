<?php

namespace Tests\Feature\MonthlyReport;

use App\Models\CorrespondenceType;
use App\Models\Drawing;
use App\Models\MeetingMinute;
use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\ProjectCorrespondence;
use App\Models\ProjectDelayNotice;
use App\Models\ProjectProgressPeriod;
use App\Models\ProjectTest;
use App\Services\MonthlyReport\ReportContext;
use App\Services\MonthlyReport\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): ReportContext
    {
        $project = Project::create(['name' => 'RTB SG. MUAR', 'code' => 'JPS/IP/BPB/10/2025', 'status' => 'in_progress']);

        ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 2, 'period_start' => '2025-11-16', 'period_end' => '2025-12-15']);
        $period3 = ProjectProgressPeriod::create(['project_id' => $project->id, 'period_no' => 3, 'period_start' => '2025-12-16', 'period_end' => '2026-01-15']);

        ProjectDelayNotice::create(['project_id' => $project->id, 'title' => 'Late Access', 'issue' => 'Access road blocked', 'reg_number' => 'DN-01', 'submitted_date' => '2025-12-20', 'submitted_via' => 'letter', 'reply_date' => '2025-12-25', 'status' => 'Close', 'impact' => 'None', 'sort_order' => 1]);
        ProjectDelayNotice::create(['project_id' => $project->id, 'title' => 'Weather Delay', 'issue' => 'Heavy rain', 'reg_number' => 'DN-02', 'submitted_date' => '2026-01-05', 'status' => 'Open', 'impact' => 'Minor', 'sort_order' => 2]);

        $adm = CorrespondenceType::create(['code' => 'ADM', 'name' => 'Administration', 'full_name' => 'Administrative Correspondence', 'report_group' => 'adm', 'sort_order' => 1, 'is_active' => true]);
        CorrespondenceType::create(['code' => 'RFI', 'name' => 'RFI', 'full_name' => 'Request For Information', 'report_group' => 'rfi', 'sort_order' => 2, 'is_active' => true]);

        ProjectCorrespondence::create(['project_id' => $project->id, 'type' => $adm->code, 'reference_no' => 'ADM/01', 'title' => 'Site instruction', 'status' => 'closed', 'raised_date' => '2025-11-01', 'actual_close_date' => '2025-11-10']);
        ProjectCorrespondence::create(['project_id' => $project->id, 'type' => $adm->code, 'reference_no' => 'ADM/02', 'title' => 'Follow up letter', 'status' => 'open', 'raised_date' => '2025-12-01']);
        ProjectCorrespondence::create(['project_id' => $project->id, 'type' => $adm->code, 'reference_no' => 'ADM/03', 'title' => 'Latest notice', 'status' => 'open', 'raised_date' => '2025-12-20']);

        ProjectTest::create(['project_id' => $project->id, 'ref_no' => 'T-01', 'name' => 'Pressure Test', 'test_date' => '2025-12-22', 'result' => 'Pass', 'remarks' => 'OK', 'sort_order' => 1]);

        Drawing::create(['project_id' => $project->id, 'title' => 'Tender Layout', 'drawing_no' => 'DWG-T-01', 'is_tender' => true, 'status' => 'approved', 'file_path' => 'drawings/dwg-t-01.pdf', 'file_name' => 'dwg-t-01.pdf']);
        Drawing::create(['project_id' => $project->id, 'title' => 'Working Drawing', 'drawing_no' => 'DWG-W-01', 'is_tender' => false, 'status' => 'approved', 'file_path' => 'drawings/dwg-w-01.pdf', 'file_name' => 'dwg-w-01.pdf']);

        MeetingMinute::create(['project_id' => $project->id, 'title' => 'Site Meeting 1', 'meeting_date' => '2025-12-05', 'location' => 'Site Office']);
        MeetingMinute::create(['project_id' => $project->id, 'title' => 'Site Meeting 2', 'meeting_date' => '2026-01-10', 'location' => 'HQ']);

        $report = MonthlyReport::create(['project_id' => $project->id, 'period_id' => $period3->id, 'report_no' => 3, 'title' => 'Monthly Progress Report No.3', 'month_label' => 'January 2026']);

        return ReportContext::for($report);
    }

    public function test_work_programme_is_a_placeholder(): void
    {
        $data = SectionRegistry::make('2.5')->build($this->context());

        $this->assertSame(1, $data['schema']);
        $this->assertSame('No work programme has been imported for this project yet (Report Data › Work Programme).', $data['note']);
        $this->assertSame([], $data['rows']);
    }

    public function test_delay_notice_rows_are_ordered_with_duration(): void
    {
        $rows = SectionRegistry::make('2.6')->build($this->context())['rows'];

        $this->assertCount(2, $rows);
        $this->assertSame('DN-01', $rows[0]['reg_number']);
        $this->assertSame("20.12.2025\nvia letter", $rows[0]['submitted']);
        $this->assertSame('25.12.2025', $rows[0]['reply']);
        $this->assertIsInt($rows[0]['duration']);
        $this->assertSame('Close', $rows[0]['status']);
        $this->assertSame('-', $rows[1]['reply']);
        $this->assertIsInt($rows[1]['duration']);
    }

    public function test_document_submission_counts_correspondence_by_type(): void
    {
        $data = SectionRegistry::make('3.1')->build($this->context());
        $groups = collect($data['groups'])->keyBy('code');

        $this->assertSame(3, $groups['ADM']['accumulative']['issued']);
        $this->assertSame(1, $groups['ADM']['accumulative']['closed']);
        $this->assertSame(2, $groups['ADM']['accumulative']['open']);
        $this->assertSame(1, $groups['ADM']['current']['issued']);
        $this->assertSame(0, $groups['RFI']['accumulative']['issued']);
        $this->assertSame('15/01/2026', $data['current_label']);
    }

    public function test_pending_correspondence_excludes_closed_rows(): void
    {
        $data = SectionRegistry::make('3.2')->build($this->context());
        $groups = collect($data['groups'])->keyBy('code');
        $admRows = $groups['ADM']['rows'];

        $this->assertCount(2, $admRows);
        $this->assertSame('MGE/JPS/ADM/(No)', $groups['ADM']['ref_prefix']);
        foreach ($admRows as $row) {
            $this->assertNotSame('ADM/01', $row['reference']);
        }
    }

    public function test_testing_rows_from_project_tests(): void
    {
        $rows = SectionRegistry::make('3.4')->build($this->context())['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('T-01', $rows[0]['ref_no']);
        $this->assertSame('Pass', $rows[0]['result']);
    }

    public function test_tender_drawing_lists_only_tender_drawings(): void
    {
        $rows = SectionRegistry::make('3.6')->build($this->context())['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('DWG-T-01', $rows[0]['drawing_no']);
    }

    public function test_meeting_list_ordered_by_date(): void
    {
        $rows = SectionRegistry::make('3.7')->build($this->context())['rows'];

        $this->assertCount(2, $rows);
        $this->assertSame('Site Meeting 1', $rows[0]['description']);
        $this->assertSame('Site Meeting 2', $rows[1]['description']);
    }
}
