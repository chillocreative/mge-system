<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterQualityRecord;
use App\Services\Environment\WaterQualityPdf;
use App\Services\Environment\WaterQualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaterQualityController extends Controller
{
    public function __construct(
        private WaterQualityService $service,
        private WaterQualityPdf $pdf,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'from', 'to', 'search', 'per_page']);

        return $this->success($this->service->list($filters));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $record = $this->service->create($data, $request->user()->id);

        return $this->created($record, 'Water quality record created.');
    }

    public function show(int $id): JsonResponse
    {
        $record = WaterQualityRecord::with(['project', 'creator', 'updater'])->findOrFail($id);

        return $this->success($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = WaterQualityRecord::findOrFail($id);

        $rules = $this->rules();
        $rules['project_id'] = ['sometimes', 'required', 'exists:projects,id'];
        $rules['sample_date'] = ['sometimes', 'required', 'date'];

        $data = $request->validate($rules);

        $record = $this->service->update($record, $data, $request->user()->id);

        return $this->success($record, 'Water quality record updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $record = WaterQualityRecord::findOrFail($id);
        $this->service->delete($record);

        return $this->success(null, 'Water quality record deleted.');
    }

    public function pdf(int $id)
    {
        $record = WaterQualityRecord::with('project')->findOrFail($id);

        return $this->pdf->build($record)->download("water-quality-worksheet-{$record->id}.pdf");
    }

    private function rules(): array
    {
        $conditionRules = [];
        foreach (WaterQualityRecord::POINTS as $point) {
            foreach (WaterQualityRecord::CONDITION_OPTIONS as $field => $options) {
                $conditionRules["conditions.{$point}.{$field}"] = ['nullable', 'string', 'in:'.implode(',', $options)];
            }
        }

        return array_merge([
            'project_id' => ['required', 'exists:projects,id'],
            'sample_date' => ['required', 'date'],
            'sample_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'data_collector' => ['nullable', 'string', 'max:255'],
            'witness' => ['nullable', 'string', 'max:255'],
            'conditions' => ['nullable', 'array'],
            'insitu' => ['nullable', 'array'],
            'insitu.*.*' => ['nullable', 'string', 'max:20'],
            'lab' => ['nullable', 'array'],
            'lab.*.*' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ], $conditionRules);
    }
}
