<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\Material;
use App\Models\ProjectSubcontractorClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['projects.view', 'projects.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function actor(): User
    {
        $u = User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => uniqid().'@test.local', 'password' => bcrypt('x'), 'status' => 'active']);
        $u->givePermissionTo(['projects.view', 'projects.edit']);

        return $u;
    }

    private function project(): Project
    {
        return Project::create(['name' => 'P', 'code' => 'P'.random_int(1000, 9999), 'status' => 'in_progress']);
    }

    public function test_claim_exposes_thirty_day_timing(): void
    {
        $claim = ProjectSubcontractorClaim::create(['project_id' => $this->project()->id, 'submitted_date' => '2026-01-01', 'paid_date' => '2026-02-05', 'amount' => 100]);
        $this->assertSame('Delay by 5 days', $claim->payment_timing);
    }

    public function test_import_keeps_same_invoice_when_do_differs(): void
    {
        $s = new Spreadsheet;
        $sheet = $s->getActiveSheet();
        $sheet->fromArray([['Invoice No', 'DO No', 'Amount', 'Date'], ['INV-1', 'DO-1', 100, '2026-01-01'], ['INV-1', 'DO-2', 200, '2026-01-01']]);
        $path = tempnam(sys_get_temp_dir(), 'pf').'.xlsx';
        (new Xlsx($s))->save($path);
        $response = $this->actingAs($this->actor())->post('/api/project-finance/import', ['project_id' => $this->project()->id, 'resource' => 'expenses', 'file' => new \Illuminate\Http\UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)]);
        $response->assertOk();
        $this->assertDatabaseCount('project_expenses', 2);
        $this->assertEqualsCanonicalizing(['DO-1', 'DO-2'], ProjectExpense::where('invoice_no', 'INV-1')->pluck('do_no')->all());
        unlink($path);
    }

    public function test_manual_expense_amount_is_calculated_on_create_and_update(): void
    {
        $project = $this->project();
        $user = $this->actor();
        Material::create(['category' => 'Materials', 'description' => 'Sand']);
        $response = $this->actingAs($user)->postJson('/api/project-finance/expenses', [
            'project_id' => $project->id, 'expense_date' => '2026-10-01', 'quantity' => 2.5,
            'category' => 'Materials', 'description' => 'Sand', 'unit' => 'm3', 'unit_price' => 12.40, 'amount' => 999, 'payment_method' => 'Bank Transfer',
        ])->assertCreated()->assertJsonPath('data.amount', '31.00')->assertJsonPath('data.amount_calculated', 31)->assertJsonPath('data.amount_source_mismatch', false);
        $id = $response->json('data.id');

        $this->actingAs($user)->putJson("/api/project-finance/expenses/{$id}", ['expense_date' => '2026-10-01', 'quantity' => 3, 'unit_price' => 12.40, 'amount' => 1])
            ->assertOk()->assertJsonPath('data.amount', '37.20');
        $this->assertDatabaseHas('project_expenses', ['id' => $id, 'amount' => 37.20, 'payment_method' => 'Bank Transfer']);
    }

    public function test_expense_import_preserves_source_amount_and_reports_mismatch_and_invalid_date(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Date', 'Category', 'Description', 'Qty', 'Unit', 'Price per Unit', 'Amount', 'Payment Method', 'Invoice No.', 'DO No.', 'Vendor'],
            ['2026-10-01', 'Materials', 'Sand', 2, 'tonne', 10, 25, 'Cash', 'INV-1', 'DO-1', 'Supplier A'],
            ['2026-10-02', 'Materials', 'Sand', 3, 'tonne', 10, 30, 'Cash', 'INV-1', 'DO-2', 'Supplier A'],
            ['not-a-date', 'Materials', 'Skipped', 1, 'tonne', 10, 10, 'Cash', 'INV-2', 'DO-3', 'Supplier A'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'pf').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        try {
            $response = $this->actingAs($this->actor())->post('/api/project-finance/import', [
                'project_id' => $this->project()->id, 'resource' => 'auto',
                'file' => new \Illuminate\Http\UploadedFile($path, 'expenses.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])->assertOk()->assertJsonPath('data.expenses', 2)->assertJsonPath('data.vendor-payments', 0);
            $this->assertSame(['amount_mismatch', 'invalid_date'], array_column($response->json('data.warnings'), 'type'));
            $first = ProjectExpense::where('do_no', 'DO-1')->firstOrFail();
            $this->assertSame('25.00', $first->amount);
            $this->assertSame('20.00', number_format($first->amount_calculated, 2, '.', ''));
            $this->assertTrue($first->amount_source_mismatch);
            $this->assertSame('Cash', $first->payment_method);
            $this->assertSame('tonne', $first->unit);
            $this->assertDatabaseHas('project_expenses', ['invoice_no' => 'INV-1', 'do_no' => 'DO-2', 'amount_source_mismatch' => false]);
            $this->assertDatabaseMissing('project_expenses', ['description' => 'Skipped']);
        } finally {
            unlink($path);
        }
    }

    public function test_expense_list_paginates_and_summarizes_filtered_rows(): void
    {
        $project = $this->project();
        foreach ([['Cash', 10, true], ['Cash', 20, false], ['Bank', 30, false]] as [$method, $amount, $mismatch]) {
            ProjectExpense::create(['project_id' => $project->id, 'expense_date' => '2026-10-01', 'category' => 'Materials', 'vendor' => 'Supplier A', 'payment_method' => $method, 'amount' => $amount, 'amount_source_mismatch' => $mismatch]);
        }
        $this->actingAs($this->actor())->getJson('/api/project-finance/expenses?project_id='.$project->id.'&month=2026-10&category=Materials&vendor=Supplier&payment_method=Cash&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.total', 2)->assertJsonPath('data.summary.count', 2)
            ->assertJsonPath('data.summary.total_amount', 30)->assertJsonPath('data.summary.mismatch_count', 1)
            ->assertJsonStructure(['data' => ['data' => [['quantity', 'unit', 'unit_price', 'amount', 'payment_method', 'invoice_no', 'do_no', 'vendor', 'amount_source_mismatch', 'amount_calculated']]]]);
    }

    public function test_expense_import_rejects_excel_serial_dates_that_land_before_project_era(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Date', 'Category', 'Description', 'Qty', 'Unit', 'Price per Unit', 'Amount'],
            [12, 'Materials', 'Bad date', 1, 'bag', 10, 10],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'pf').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $response = $this->actingAs($this->actor())->post('/api/project-finance/import', [
                'project_id' => $this->project()->id, 'resource' => 'expenses',
                'file' => new \Illuminate\Http\UploadedFile($path, 'expenses.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])->assertOk();

            $response->assertJsonPath('data.expenses', 0)->assertJsonPath('data.warnings.0.type', 'invalid_date');
            $this->assertDatabaseMissing('project_expenses', ['description' => 'Bad date']);
        } finally {
            unlink($path);
        }
    }

    public function test_expense_pdf_export_renders_workbook_columns(): void
    {
        ProjectExpense::create(['project_id' => $this->project()->id, 'expense_date' => '2026-10-01', 'category' => 'Materials', 'quantity' => 2, 'unit_price' => 10, 'amount' => 20]);

        $response = $this->actingAs($this->actor())->get('/api/project-finance/expenses/export?format=pdf');
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
