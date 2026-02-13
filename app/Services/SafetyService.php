<?php

namespace App\Services;

use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\HazardReport;
use App\Models\ReportPhoto;
use App\Models\SafetyIncident;
use App\Models\ToolboxMeeting;
use App\Models\ToolboxMeetingAttendee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SafetyService
{
    // ── Incidents ──

    public function listIncidents(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = SafetyIncident::with(['project:id,name,code', 'reporter:id,first_name,last_name', 'photos'])
            ->orderByDesc('incident_date');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['severity'])) $query->bySeverity($filters['severity']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function createIncident(array $data, int $userId, array $photos = []): SafetyIncident
    {
        return DB::transaction(function () use ($data, $userId, $photos) {
            $data['reported_by'] = $userId;
            $incident = SafetyIncident::create($data);
            $this->storePhotos($incident, $photos);
            return $incident->load(['project:id,name', 'reporter:id,first_name,last_name', 'photos']);
        });
    }

    public function updateIncident(int $id, array $data): SafetyIncident
    {
        $incident = SafetyIncident::findOrFail($id);
        if (!empty($data['status']) && $data['status'] === 'closed' && !$incident->closed_at) {
            $data['closed_at'] = now();
        }
        $incident->update($data);
        return $incident->load(['project:id,name', 'reporter:id,first_name,last_name', 'photos']);
    }

    public function getIncident(int $id): SafetyIncident
    {
        return SafetyIncident::with(['project:id,name,code', 'reporter:id,first_name,last_name', 'investigator:id,first_name,last_name', 'photos'])->findOrFail($id);
    }

    // ── Hazards ──

    public function listHazards(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = HazardReport::with(['project:id,name,code', 'reporter:id,first_name,last_name', 'assignee:id,first_name,last_name', 'photos'])
            ->orderByDesc('created_at');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['status'])) $query->byStatus($filters['status']);
        if (!empty($filters['risk_level'])) $query->byRisk($filters['risk_level']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function createHazard(array $data, int $userId, array $photos = []): HazardReport
    {
        return DB::transaction(function () use ($data, $userId, $photos) {
            $data['reported_by'] = $userId;
            $hazard = HazardReport::create($data);
            $this->storePhotos($hazard, $photos);
            return $hazard->load(['project:id,name', 'reporter:id,first_name,last_name', 'photos']);
        });
    }

    public function updateHazard(int $id, array $data): HazardReport
    {
        $hazard = HazardReport::findOrFail($id);
        if (!empty($data['status']) && $data['status'] === 'resolved' && !$hazard->resolved_at) {
            $data['resolved_at'] = now();
        }
        $hazard->update($data);
        return $hazard->load(['project:id,name', 'reporter:id,first_name,last_name', 'photos']);
    }

    // ── Toolbox Meetings ──

    public function listMeetings(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ToolboxMeeting::with(['project:id,name,code', 'conductor:id,first_name,last_name', 'photos'])
            ->withCount('attendees')
            ->orderByDesc('meeting_date');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$filters['search']}%")
                ->orWhere('topics', 'like', "%{$filters['search']}%"));
        }

        return $query->paginate($perPage);
    }

    public function createMeeting(array $data, int $userId, array $attendeeIds = [], array $externalNames = [], array $photos = []): ToolboxMeeting
    {
        return DB::transaction(function () use ($data, $userId, $attendeeIds, $externalNames, $photos) {
            $data['conducted_by'] = $userId;
            $meeting = ToolboxMeeting::create($data);

            foreach ($attendeeIds as $id) {
                ToolboxMeetingAttendee::create(['toolbox_meeting_id' => $meeting->id, 'user_id' => $id]);
            }
            foreach ($externalNames as $name) {
                if ($name) ToolboxMeetingAttendee::create(['toolbox_meeting_id' => $meeting->id, 'name' => $name]);
            }

            $this->storePhotos($meeting, $photos);
            return $meeting->load(['conductor:id,first_name,last_name', 'attendees.user:id,first_name,last_name', 'photos']);
        });
    }

    public function getMeeting(int $id): ToolboxMeeting
    {
        return ToolboxMeeting::with(['project:id,name,code', 'conductor:id,first_name,last_name', 'attendees.user:id,first_name,last_name', 'photos'])->findOrFail($id);
    }

    // ── Compliance Checklists ──

    public function listChecklists(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ComplianceChecklist::with(['project:id,name,code', 'inspector:id,first_name,last_name'])
            ->withCount('items')
            ->orderByDesc('checklist_date');

        if (!empty($filters['project_id'])) $query->forProject($filters['project_id']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['overall_status'])) $query->where('overall_status', $filters['overall_status']);

        return $query->paginate($perPage);
    }

    public function createChecklist(array $data, int $userId, array $items = [], array $photos = []): ComplianceChecklist
    {
        return DB::transaction(function () use ($data, $userId, $items, $photos) {
            $data['inspector_id'] = $userId;
            $checklist = ComplianceChecklist::create($data);

            foreach ($items as $i => $item) {
                ComplianceChecklistItem::create([
                    'checklist_id' => $checklist->id,
                    'item_text' => $item['item_text'],
                    'status' => $item['status'] ?? 'na',
                    'notes' => $item['notes'] ?? null,
                    'sort_order' => $i,
                ]);
            }

            $checklist->recalculateStatus();
            $this->storePhotos($checklist, $photos);
            return $checklist->load(['inspector:id,first_name,last_name', 'items', 'photos']);
        });
    }

    public function getChecklist(int $id): ComplianceChecklist
    {
        return ComplianceChecklist::with(['project:id,name,code', 'inspector:id,first_name,last_name', 'items', 'photos'])->findOrFail($id);
    }

    // ── Overview Stats ──

    public function getOverviewStats(?int $projectId = null): array
    {
        $incidentQ = SafetyIncident::query();
        $hazardQ = HazardReport::query();
        $meetingQ = ToolboxMeeting::query();
        $checklistQ = ComplianceChecklist::query();

        if ($projectId) {
            $incidentQ->forProject($projectId);
            $hazardQ->forProject($projectId);
            $meetingQ->forProject($projectId);
            $checklistQ->forProject($projectId);
        }

        return [
            'open_incidents' => (clone $incidentQ)->whereIn('status', ['open', 'investigating'])->count(),
            'critical_incidents' => (clone $incidentQ)->bySeverity('critical')->whereIn('status', ['open', 'investigating'])->count(),
            'open_hazards' => (clone $hazardQ)->whereIn('status', ['open', 'mitigated'])->count(),
            'high_risk_hazards' => (clone $hazardQ)->whereIn('risk_level', ['high', 'critical'])->whereIn('status', ['open', 'mitigated'])->count(),
            'meetings_this_month' => (clone $meetingQ)->whereMonth('meeting_date', now()->month)->whereYear('meeting_date', now()->year)->count(),
            'non_compliant_checklists' => (clone $checklistQ)->where('overall_status', 'non_compliant')->count(),
            'total_incidents' => (clone $incidentQ)->count(),
            'total_meetings' => (clone $meetingQ)->count(),
        ];
    }

    // ── PDF ──

    public function generateIncidentPdf(int $id)
    {
        $record = $this->getIncident($id);
        return Pdf::loadView('pdf.safety-report', ['record' => $record, 'type' => 'Incident Report'])->setPaper('a4');
    }

    public function generateHazardPdf(int $id)
    {
        $hazard = HazardReport::with(['project:id,name,code', 'reporter:id,first_name,last_name', 'assignee:id,first_name,last_name', 'photos'])->findOrFail($id);
        return Pdf::loadView('pdf.safety-report', ['record' => $hazard, 'type' => 'Hazard Report'])->setPaper('a4');
    }

    public function generateMeetingPdf(int $id)
    {
        $meeting = $this->getMeeting($id);
        return Pdf::loadView('pdf.safety-report', ['record' => $meeting, 'type' => 'Toolbox Meeting Log'])->setPaper('a4');
    }

    public function generateChecklistPdf(int $id)
    {
        $checklist = $this->getChecklist($id);
        return Pdf::loadView('pdf.safety-report', ['record' => $checklist, 'type' => 'Compliance Checklist'])->setPaper('a4');
    }

    // ── Photos Helper ──

    public function uploadPhotos($model, array $files): void
    {
        $this->storePhotos($model, $files);
    }

    private function storePhotos($model, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('report-photos', 'public');
            $model->photos()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
