<?php
namespace App\Services;

use App\Models\{ProjectExpense,ProjectBudget,ProjectVendorPayment,ProjectSubcontractorClaim};
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectFinanceService
{
    private array $map = ['expenses'=>ProjectExpense::class,'budgets'=>ProjectBudget::class,'vendor-payments'=>ProjectVendorPayment::class,'subcontractor-claims'=>ProjectSubcontractorClaim::class];
    public function model(string $resource): string { return $this->map[$resource] ?? throw new \InvalidArgumentException('Unknown finance resource.'); }
    public function list(string $resource, array $filters, int $perPage=20) {
        $model=$this->model($resource); $q=$model::with('project:id,name,code')->latest();
        if(!empty($filters['project_id'])) $q->where('project_id',$filters['project_id']);
        if(!empty($filters['month'])) { $date=Carbon::createFromFormat('Y-m',$filters['month']); $column=$resource==='expenses'?'expense_date':($resource==='budgets'?'month':'submitted_date'); $q->whereYear($column,$date->year)->whereMonth($column,$date->month); }
        if(!empty($filters['category']) && $resource==='expenses') $q->where('category',$filters['category']);
        if(!empty($filters['vendor']) && in_array($resource,['expenses','vendor-payments'],true)) $q->where('vendor','like','%'.$filters['vendor'].'%');
        return $q->paginate($perPage);
    }
    public function create(string $resource,array $data,int $userId){$data['created_by']=$userId; return $this->model($resource)::create($data)->load('project:id,name,code');}
    public function update(string $resource,int $id,array $data){$m=$this->model($resource)::findOrFail($id);$m->update($data);return $m->fresh('project:id,name,code');}
    public function delete(string $resource,int $id): void {$this->model($resource)::findOrFail($id)->delete();}
    public function chart(array $filters): array {
        if (!empty($filters['month'])) {
            $month = Carbon::createFromFormat('Y-m', $filters['month']);
            $filters['from'] = $month->copy()->startOfMonth()->toDateString();
            $filters['to'] = $month->copy()->endOfMonth()->toDateString();
        }
        $q=function($model,$dateColumn,$sumColumn) use ($filters){$x=$model::query(); if(!empty($filters['project_id'])) $x->where('project_id',$filters['project_id']); if(!empty($filters['from'])) $x->whereDate($dateColumn,'>=',$filters['from']); if(!empty($filters['to'])) $x->whereDate($dateColumn,'<=',$filters['to']); if($model===ProjectExpense::class){if(!empty($filters['category']))$x->where('category',$filters['category']);if(!empty($filters['vendor']))$x->where('vendor','like','%'.$filters['vendor'].'%');} if($model===ProjectSubcontractorClaim::class&&!empty($filters['vendor']))$x->where('subcontractor','like','%'.$filters['vendor'].'%'); return $x->selectRaw("DATE_FORMAT($dateColumn,'%Y-%m') as month, SUM($sumColumn) as total")->groupBy('month')->orderBy('month')->pluck('total','month');};
        $budget=$q(ProjectBudget::class,'month','budgeted_cost'); $actual=$q(ProjectExpense::class,'expense_date','amount'); $claims=$q(ProjectSubcontractorClaim::class,'submitted_date','certified_amount'); $months=collect($budget->keys())->merge($actual->keys())->merge($claims->keys())->unique()->sort()->values();
        return $months->map(fn($m)=>['month'=>$m,'budgeted_cost'=>(float)($budget[$m]??0),'actual_cost'=>(float)($actual[$m]??0),'certified_claims'=>(float)($claims[$m]??0)])->all();
    }
    public function import(UploadedFile $file,int $projectId,int $userId, string $resource = 'auto'): array {
        $spreadsheet=IOFactory::load($file->getRealPath()); $counts=['expenses'=>0,'vendor-payments'=>0,'subcontractor-claims'=>0];
        DB::transaction(function() use($spreadsheet,$projectId,$userId,$resource,&$counts){ foreach($spreadsheet->getWorksheetIterator() as $sheet){$rows=$sheet->toArray(null,true,true,true); if(count($rows)<2) continue; $headerIndex=$this->findHeaderRow($rows); $headerRow=null; for($skip=0;$skip<=$headerIndex;$skip++) $headerRow=array_shift($rows); $second=array_shift($rows); if($this->isHeaderRow($second)){ $base=array_values($headerRow ?? []); $secondValues=array_values($second); $headers=[]; foreach($secondValues as $i=>$value){$headers[]=$this->header((string)($value !== '' ? $value : ($base[$i] ?? '')));} } else { if($second!==null) array_unshift($rows,$second); $headers=array_map(fn($v)=>$this->header((string)$v),array_values($headerRow ?? [])); } foreach($rows as $row){$row=array_values($row);$r=[];foreach($headers as $i=>$h)if($h!=='')$r[$h]=trim((string)($row[$i]??'')); if(!array_filter($r))continue; $invoice=$this->value($r,['invoice no','invoice number','invoice']);$do=$this->value($r,['do no','do number','do']); $date=$this->date($this->value($r,['date','date submitted invoice','invoice date','date payment','payment date','date of payment','claim date']));$amount=$this->number($this->value($r,['amount','claim amount','total','value','payment'])); $common=['project_id'=>$projectId,'created_by'=>$userId,'invoice_no'=>$invoice?:null,'do_no'=>$do?:null,'amount'=>$amount]; $kind=$resource==='auto'?($this->looksLikeClaim($r,$sheet->getTitle())?'subcontractor-claims':($this->looksLikePayment($r,$sheet->getTitle())?'vendor-payments':'expenses')):$resource; if($kind==='subcontractor-claims'){ProjectSubcontractorClaim::create($common+['subcontractor'=>$this->value($r,['subcontractor','vendor','payee']),'claim_no'=>$this->value($r,['claim no','claim number']),'submitted_date'=>$date,'certified_amount'=>$this->number($this->value($r,['certified claims','certified amount','certified amt','certified']))]);$counts['subcontractor-claims']++;} elseif($kind==='vendor-payments'){ProjectVendorPayment::create($common+['vendor'=>$this->value($r,['vendor','supplier','payee']),'invoice_date'=>$date,'submitted_date'=>$date,'paid_date'=>$this->date($this->value($r,['paid date','date payment','payment date','date of payment']))]);$counts['vendor-payments']++;} else {ProjectExpense::create($common+['expense_date'=>$date?:now()->toDateString(),'category'=>$this->value($r,['category','type']),'description'=>$this->value($r,['description','details','particulars']),'vendor'=>$this->value($r,['vendor','supplier','payee'])]);$counts['expenses']++;}}}}); return $counts;
    }
    private function header(string $value): string { return strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/i', ' ', $value)))); }
    private function isHeaderRow(?array $row): bool { if(!$row)return false; $s=strtolower(implode(' ',array_map(fn($v)=>$this->header((string)$v),array_values($row)))); $hits=0; foreach(['date','amount','invoice','claim','vendor','sub contractor','description','payment'] as $word) if(str_contains($s,$word))$hits++; return $hits>=2; }
    private function findHeaderRow(array $rows): int { foreach($rows as $index=>$row){ $values=array_map(fn($v)=>$this->header((string)$v),array_values($row)); $joined=implode(' ', $values); if(str_contains($joined,'amount') && (str_contains($joined,'date') || str_contains($joined,'invoice') || str_contains($joined,'claim'))) return (int)$index-1; } return 0; }
    private function value(array $r,array $keys): string {foreach($keys as $k){$k=$this->header($k);foreach($r as $header=>$value)if($header===$k&&$value!=='')return $value;}foreach($keys as $k){$k=$this->header($k);if(!str_contains($k,' ')||strlen($k)<6)continue;foreach($r as $header=>$value)if(str_contains($header,$k)&&$value!=='')return $value;}return '';}
    private function number(string $v): float {return (float)str_replace([',','RM',' '],'',$v);}
    private function date(string $v): ?string {if($v==='')return null;try{return is_numeric($v)?Carbon::create(1899,12,30)->addDays((int)$v)->toDateString():Carbon::parse($v)->toDateString();}catch(\Throwable){return null;}}
    private function looksLikeClaim(array $r,string $title): bool {$s=strtolower($title.' '.implode(' ',array_keys($r)));return str_contains($s,'claim')||str_contains($s,'subcont');}
    private function looksLikePayment(array $r,string $title): bool {$s=strtolower($title.' '.implode(' ',array_keys($r)));return str_contains($s,'vendor')||str_contains($s,'payment');}
}
