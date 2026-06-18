<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $maintenanceService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status', 'maintenance_type', 'maintainable_type', 'maintainable_id', 'search',
        ]);
        $perPage = min($request->integer('per_page', 15), 100);
        return $this->success($this->maintenanceService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'maintainable_type' => ['required', 'string', 'max:255'],
            'maintainable_id' => ['required', 'integer'],
            'maintenance_type' => ['required', 'in:preventive,corrective,emergency'],
            'performed_date' => ['required', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'description' => ['required', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:planned,in_progress,completed'],
        ]);

        return $this->created($this->maintenanceService->create($validated, $request->user()->id), 'Maintenance log created.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->maintenanceService->get($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'maintenance_type' => ['sometimes', 'in:preventive,corrective,emergency'],
            'performed_date' => ['sometimes', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'description' => ['sometimes', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:planned,in_progress,completed'],
        ]);

        return $this->success($this->maintenanceService->update($id, $validated), 'Maintenance log updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->maintenanceService->delete($id);
        return $this->success(null, 'Maintenance log deleted.');
    }

    public function upcoming(Request $request): JsonResponse
    {
        $days = min($request->integer('days', 30), 365);
        return $this->success($this->maintenanceService->upcoming($days));
    }
}
