<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProjectFinanceExport;
use App\Exports\ProjectFinanceReportExport;
use App\Http\Controllers\Controller;
use App\Services\ProjectFinanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProjectFinanceController extends Controller
{
    public function __construct(private ProjectFinanceService $service) {}

    public function index(Request $request, string $resource)
    {
        return $this->success($this->service->list($resource, $request->only(['project_id', 'month', 'category', 'vendor']), min($request->integer('per_page', 20), 100)));
    }

    public function store(Request $request, string $resource)
    {
        $data = $this->validateData($request, $resource);

        return $this->created($this->service->create($resource, $data, $request->user()->id), 'Finance record created.');
    }

    public function update(Request $request, string $resource, int $id)
    {
        return $this->success($this->service->update($resource, $id, $this->validateData($request, $resource, true)), 'Finance record updated.');
    }

    public function destroy(string $resource, int $id)
    {
        $this->service->delete($resource, $id);

        return $this->success(null, 'Finance record deleted.');
    }

    public function chart(Request $request)
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'project_id' => ['nullable', 'exists:projects,id']]);

        return $this->success($this->service->chart($request->all()));
    }

    public function import(Request $request)
    {
        $data = $request->validate(['project_id' => ['required', 'exists:projects,id'], 'file' => ['required', 'file', 'extensions:xlsx,xls'], 'resource' => ['nullable', 'in:auto,expenses,vendor-payments,subcontractor-claims']]);

        return $this->success($this->service->import($request->file('file'), $data['project_id'], $request->user()->id, $data['resource'] ?? 'auto'), 'Finance workbook imported.');
    }

    public function export(Request $request, string $resource)
    {
        $filters = $request->only(['project_id', 'month', 'category', 'vendor', 'from', 'to']);
        $format = $request->get('format', 'xlsx');
        $name = 'project-finance-'.$resource.'-'.now()->format('Ymd');
        if ($resource === 'reports') {
            $rows = $this->service->chart($filters);
            if ($format === 'pdf') {
                return Pdf::loadView('pdf.project-finance', ['resource' => 'reports', 'rows' => collect($rows)])->download($name.'.pdf');
            }

            return Excel::download(new ProjectFinanceReportExport($rows), $name.'.xlsx');
        }$rows = $this->service->list($resource, $filters, 10000)->getCollection();
        if ($format === 'pdf') {
            return Pdf::loadView('pdf.project-finance', ['resource' => $resource, 'rows' => $rows])->download($name.'.pdf');
        }

        return Excel::download(new ProjectFinanceExport($resource, $rows), $name.'.xlsx');
    }

    private function validateData(Request $request, string $resource, bool $update = false): array
    {
        $rules = ['project_id' => [$update ? 'sometimes' : 'required', 'exists:projects,id'], 'amount' => ['nullable', 'numeric', 'min:0']];
        if ($resource === 'expenses') {
            $rules += ['expense_date' => ['required', 'date'], 'category' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string'], 'vendor' => ['nullable', 'string', 'max:255'], 'invoice_no' => ['nullable', 'string', 'max:255'], 'do_no' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string']];
        } elseif ($resource === 'budgets') {
            $rules += ['month' => ['required', 'date_format:Y-m'], 'category' => ['nullable', 'string', 'max:100'], 'budgeted_cost' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string']];
        } elseif ($resource === 'vendor-payments') {
            $rules += ['vendor' => ['nullable', 'string', 'max:255'], 'invoice_no' => ['nullable', 'string', 'max:255'], 'do_no' => ['nullable', 'string', 'max:255'], 'invoice_date' => ['nullable', 'date'], 'submitted_date' => ['nullable', 'date'], 'due_date' => ['nullable', 'date'], 'paid_date' => ['nullable', 'date'], 'status' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string']];
        } else {
            $rules += ['subcontractor' => ['nullable', 'string', 'max:255'], 'claim_no' => ['nullable', 'string', 'max:255'], 'invoice_no' => ['nullable', 'string', 'max:255'], 'do_no' => ['nullable', 'string', 'max:255'], 'submitted_date' => ['nullable', 'date'], 'certified_date' => ['nullable', 'date'], 'paid_date' => ['nullable', 'date'], 'certified_amount' => ['nullable', 'numeric', 'min:0'], 'status' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string']];
        } $data = $request->validate($rules);
        if ($resource === 'budgets' && isset($data['month'])) {
            $data['month'] .= '-01';
        }

        return $data;
    }
}
