<?php
namespace Tests\Feature;

use App\Models\{Project,ProjectSubcontractorClaim,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectFinanceTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); foreach(['projects.view','projects.edit'] as $p) Permission::findOrCreate($p,'web'); }
    private function actor(): User { $u=User::create(['first_name'=>'A','last_name'=>'B','email'=>uniqid().'@test.local','password'=>bcrypt('x'),'status'=>'active']);$u->givePermissionTo(['projects.view','projects.edit']);return $u; }
    private function project(): Project { return Project::create(['name'=>'P','code'=>'P'.random_int(1000,9999),'status'=>'in_progress']); }
    public function test_claim_exposes_thirty_day_timing(): void { $claim=ProjectSubcontractorClaim::create(['project_id'=>$this->project()->id,'submitted_date'=>'2026-01-01','paid_date'=>'2026-02-05','amount'=>100]); $this->assertSame('Delay by 5 days',$claim->payment_timing); }
    public function test_import_keeps_same_invoice_when_do_differs(): void { $s=new Spreadsheet();$sheet=$s->getActiveSheet();$sheet->fromArray([['Invoice No','DO No','Amount','Date'],['INV-1','DO-1',100,'2026-01-01'],['INV-1','DO-2',200,'2026-01-01']]);$path=tempnam(sys_get_temp_dir(),'pf').'.xlsx';(new Xlsx($s))->save($path);$response=$this->actingAs($this->actor())->post('/api/project-finance/import',['project_id'=>$this->project()->id,'resource'=>'expenses','file'=>new \Illuminate\Http\UploadedFile($path,'import.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',null,true)]);$response->assertOk();$this->assertDatabaseCount('project_expenses',2);unlink($path); }
}
