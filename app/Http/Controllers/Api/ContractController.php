<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(private ContractService $contractService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $filters = $request->only(['project_id', 'status', 'search']);

        return $this->success($this->contractService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        $files = $request->file('files', []);
        unset($validated['files']);

        $contract = $this->contractService->create($validated, $request->user()->id, $files);

        return $this->created($contract, 'Contract created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->contractService->getOne($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validatePayload($request, false);

        $files = $request->file('files', []);
        unset($validated['files']);

        $contract = $this->contractService->update($id, $validated, $files);

        return $this->success($contract, 'Contract updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->contractService->delete($id);

        return $this->success(null, 'Contract deleted successfully.');
    }

    public function storeFiles(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $contract = $this->contractService->addFiles($id, $request->file('files', []));

        return $this->success($contract, 'Files uploaded successfully.');
    }

    public function downloadFile(int $fileId)
    {
        return $this->contractService->downloadFile($fileId);
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'project_id' => [$required, 'exists:projects,id'],
            'title' => [$required, 'string', 'max:255'],
            'contract_no' => ['nullable', 'string', 'max:255'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'pic_email' => ['nullable', 'email', 'max:255'],
            'pic_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,completed,terminated'],
            'notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);
    }
}
