<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafetyController extends Controller
{
    public function __construct(private SafetyService $safetyService) {}

    // ── Overview Stats ──

    public function overview(Request $request): JsonResponse
    {
        $stats = $this->safetyService->getOverviewStats($request->integer('project_id') ?: null);
        return $this->success($stats);
    }

    // ── Incidents ──

    public function incidents(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'status', 'severity', 'search']);
        return $this->success($this->safetyService->listIncidents($filters, $request->integer('per_page', 15)));
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'incident_date' => ['required', 'date'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', 'in:minor,moderate,serious,critical'],
            'type' => ['required', 'in:injury,near_miss,property_damage,environmental,fire,other'],
            'injured_person' => ['nullable', 'string', 'max:255'],
            'injury_description' => ['nullable', 'string'],
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'preventive_action' => ['nullable', 'string'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        unset($validated['photos']);

        $incident = $this->safetyService->createIncident($validated, $request->user()->id, $photos);
        return $this->created($incident, 'Incident reported successfully.');
    }

    public function showIncident(int $id): JsonResponse
    {
        return $this->success($this->safetyService->getIncident($id));
    }

    public function updateIncident(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'severity' => ['sometimes', 'in:minor,moderate,serious,critical'],
            'type' => ['sometimes', 'in:injury,near_miss,property_damage,environmental,fire,other'],
            'status' => ['sometimes', 'in:open,investigating,resolved,closed'],
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'preventive_action' => ['nullable', 'string'],
            'investigated_by' => ['nullable', 'exists:users,id'],
        ]);

        return $this->success($this->safetyService->updateIncident($id, $validated));
    }

    // ── Hazards ──

    public function hazards(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'status', 'risk_level', 'search']);
        return $this->success($this->safetyService->listHazards($filters, $request->integer('per_page', 15)));
    }

    public function storeHazard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'hazard_type' => ['required', 'in:fall,electrical,chemical,structural,equipment,fire,ergonomic,other'],
            'risk_level' => ['required', 'in:low,medium,high,critical'],
            'recommended_action' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        unset($validated['photos']);

        $hazard = $this->safetyService->createHazard($validated, $request->user()->id, $photos);
        return $this->created($hazard, 'Hazard reported successfully.');
    }

    public function updateHazard(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'risk_level' => ['sometimes', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'in:open,mitigated,resolved,closed'],
            'corrective_action' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        return $this->success($this->safetyService->updateHazard($id, $validated));
    }

    // ── Toolbox Meetings ──

    public function meetings(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'search']);
        return $this->success($this->safetyService->listMeetings($filters, $request->integer('per_page', 15)));
    }

    public function storeMeeting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'topics' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'action_items' => ['nullable', 'string'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['exists:users,id'],
            'external_names' => ['nullable', 'array'],
            'external_names.*' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        $attendeeIds = $validated['attendee_ids'] ?? [];
        $externalNames = $validated['external_names'] ?? [];
        unset($validated['photos'], $validated['attendee_ids'], $validated['external_names']);

        $meeting = $this->safetyService->createMeeting($validated, $request->user()->id, $attendeeIds, $externalNames, $photos);
        return $this->created($meeting, 'Meeting logged successfully.');
    }

    public function showMeeting(int $id): JsonResponse
    {
        return $this->success($this->safetyService->getMeeting($id));
    }

    // ── Compliance Checklists ──

    public function checklists(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'type', 'overall_status']);
        return $this->success($this->safetyService->listChecklists($filters, $request->integer('per_page', 15)));
    }

    public function storeChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:osha,fire_safety,ppe,scaffolding,electrical,excavation,general,custom'],
            'checklist_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_text' => ['required', 'string', 'max:500'],
            'items.*.status' => ['required', 'in:pass,fail,na'],
            'items.*.notes' => ['nullable', 'string'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        $items = $validated['items'];
        unset($validated['photos'], $validated['items']);

        $checklist = $this->safetyService->createChecklist($validated, $request->user()->id, $items, $photos);
        return $this->created($checklist, 'Checklist created successfully.');
    }

    public function showChecklist(int $id): JsonResponse
    {
        return $this->success($this->safetyService->getChecklist($id));
    }

    // ── PDF Reports ──

    public function incidentPdf(int $id)
    {
        return $this->safetyService->generateIncidentPdf($id)->download("incident-report-{$id}.pdf");
    }

    public function hazardPdf(int $id)
    {
        return $this->safetyService->generateHazardPdf($id)->download("hazard-report-{$id}.pdf");
    }

    public function meetingPdf(int $id)
    {
        return $this->safetyService->generateMeetingPdf($id)->download("meeting-log-{$id}.pdf");
    }

    public function checklistPdf(int $id)
    {
        return $this->safetyService->generateChecklistPdf($id)->download("compliance-checklist-{$id}.pdf");
    }

    // ── Photo Upload ──

    public function uploadPhotos(Request $request, string $type, int $id): JsonResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $modelMap = [
            'incidents' => \App\Models\SafetyIncident::class,
            'hazards' => \App\Models\HazardReport::class,
            'meetings' => \App\Models\ToolboxMeeting::class,
            'checklists' => \App\Models\ComplianceChecklist::class,
        ];

        if (!isset($modelMap[$type])) {
            return $this->error('Invalid resource type.', 422);
        }

        $model = $modelMap[$type]::findOrFail($id);
        $this->safetyService->uploadPhotos($model, $request->file('photos'));

        return $this->success($model->load('photos'), 'Photos uploaded successfully.');
    }
}
