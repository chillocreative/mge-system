<?php

namespace Tests\Feature\Uploads;

use App\Exports\BoqTemplateExport;
use App\Models\ContractBoqItem;
use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Ciri 5 — BQ import against the official template (plan §5.8).
 */
class BoqImportTest extends TestCase
{
    use RefreshDatabase;

    private function contract(): ProjectContract
    {
        $project = Project::create(['name' => 'P', 'code' => 'P'.uniqid(), 'status' => 'in_progress']);

        return ProjectContract::create(['project_id' => $project->id, 'title' => 'C', 'status' => 'active']);
    }

    private function actor(): User
    {
        return User::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'u-'.uniqid().'@mge-eng.com', 'password' => bcrypt('x')]);
    }

    /** Build a real .xlsx in the template's shape and return an UploadedFile. */
    private function sheet(array $rows): UploadedFile
    {
        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            public function __construct(private array $rows) {}

            public function headings(): array
            {
                return ['Item No', 'Description', 'Unit', 'Quantity', 'Rate (RM)'];
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        $path = sys_get_temp_dir().'/bq-'.uniqid().'.xlsx';
        Excel::store($export, basename($path), 'local');

        // Excel::store writes to the local disk root; read it back into an UploadedFile.
        $stored = storage_path('app/private/'.basename($path));
        if (! file_exists($stored)) {
            $stored = storage_path('app/'.basename($path));
        }

        return new UploadedFile($stored, 'bq.xlsx', null, null, true);
    }

    public function test_it_imports_rows_and_computes_amount(): void
    {
        $contract = $this->contract();
        $file = $this->sheet([
            ['A.1', 'Site clearing', 'm2', 1200, 3.5],
            ['A.2', 'Temp office', 'unit', 1, 5000],
        ]);

        $result = app(ContractService::class)->importBoq($contract->id, $file, $this->actor()->id);

        $this->assertSame(2, $result['imported']);
        $items = ContractBoqItem::where('project_contract_id', $contract->id)->orderBy('sort_order')->get();
        $this->assertSame('Site clearing', $items[0]->description);
        // Amount is Qty x Rate, computed on import.
        $this->assertSame('4200.00', (string) $items[0]->amount);
        $this->assertSame('5000.00', (string) $items[1]->amount);
    }

    public function test_blank_description_rows_are_skipped(): void
    {
        $contract = $this->contract();
        $file = $this->sheet([
            ['A', '', '', '', ''],          // spacer row
            ['A.1', 'Real item', 'm', 10, 2],
        ]);

        $result = app(ContractService::class)->importBoq($contract->id, $file, $this->actor()->id);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_it_tolerates_thousands_separators(): void
    {
        $contract = $this->contract();
        $file = $this->sheet([['B.1', 'Excavation', 'm3', '1,200', '28.00']]);

        app(ContractService::class)->importBoq($contract->id, $file, $this->actor()->id);

        $item = ContractBoqItem::where('project_contract_id', $contract->id)->sole();
        $this->assertSame('1200.00', (string) $item->quantity);
        $this->assertSame('33600.00', (string) $item->amount);
    }

    public function test_import_appends_after_existing_items(): void
    {
        $contract = $this->contract();
        ContractBoqItem::create(['project_contract_id' => $contract->id, 'description' => 'Existing', 'quantity' => 1, 'rate' => 1, 'amount' => 1, 'sort_order' => 0]);

        app(ContractService::class)->importBoq($contract->id, $this->sheet([['X', 'New item', 'm', 2, 2]]), $this->actor()->id);

        $items = ContractBoqItem::where('project_contract_id', $contract->id)->orderBy('sort_order')->pluck('description')->all();
        $this->assertSame(['Existing', 'New item'], $items);
    }

    public function test_the_template_export_has_the_expected_headings(): void
    {
        $this->assertSame(
            ['Item No', 'Description', 'Unit', 'Quantity', 'Rate (RM)'],
            (new BoqTemplateExport)->headings(),
        );
    }
}
