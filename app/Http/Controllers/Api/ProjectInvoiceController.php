<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectInvoiceController extends Controller
{
    public function __construct(private ProjectInvoiceService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $filters = $request->only(['project_id', 'type', 'status', 'search']);

        return $this->success($this->service->list($filters, $perPage));
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->service->summary($request->only(['project_id'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'type' => ['required', 'in:client,subcon'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,submitted,approved,paid'],
            'client_approved_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $files = $request->file('files', []);
        $invoice = $this->service->create($data, $request->user()->id, $files);

        return $this->created($invoice, 'Invoice created.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->service->getOne($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'exists:projects,id'],
            'type' => ['sometimes', 'required', 'in:client,subcon'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['sometimes', 'required', 'date'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', 'in:draft,submitted,approved,paid'],
            'client_approved_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $files = $request->file('files', []);
        $invoice = $this->service->update($id, $data, $files);

        return $this->success($invoice, 'Invoice updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Invoice deleted.');
    }

    public function storeFiles(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $invoice = $this->service->storeFilesForInvoice($id, $request->file('files', []));

        return $this->success($invoice, 'Files uploaded.');
    }

    public function downloadFile(int $fileId)
    {
        return $this->service->downloadFile($fileId);
    }

    public function destroyFile(int $fileId): JsonResponse
    {
        $this->service->deleteFile($fileId);

        return $this->success(null, 'File deleted.');
    }
}
