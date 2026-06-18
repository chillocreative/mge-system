<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function __construct(private MeetingService $meetingService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);

        return $this->success($this->meetingService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        $files = $request->file('files', []);
        unset($validated['files']);

        $meeting = $this->meetingService->create($validated, $request->user()->id, $files);

        return $this->created($meeting, 'Meeting minutes created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->meetingService->getOne($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validatePayload($request, false);

        $files = $request->file('files', []);
        unset($validated['files']);

        $meeting = $this->meetingService->update($id, $validated, $files);

        return $this->success($meeting, 'Meeting minutes updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->meetingService->delete($id);

        return $this->success(null, 'Meeting minutes deleted successfully.');
    }

    public function storeFiles(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx'],
        ]);

        $meeting = $this->meetingService->addFiles($id, $request->file('files', []));

        return $this->success($meeting, 'Files uploaded successfully.');
    }

    public function downloadFile(int $fileId)
    {
        return $this->meetingService->fileDownload($fileId);
    }

    public function employees(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->select('id', 'employee_no', 'first_name', 'last_name')
            ->orderBy('first_name')
            ->get();

        return $this->success($employees);
    }

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'meeting_date' => [$required, 'date'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'attendees' => ['nullable', 'array'],
            'attendees.*.employee_id' => ['nullable', 'exists:employees,id'],
            'attendees.*.user_id' => ['nullable', 'exists:users,id'],
            'attendees.*.name' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'action_items' => ['nullable', 'array'],
            'action_items.*.item' => ['required_with:action_items', 'string'],
            'action_items.*.assigned_to' => ['nullable', 'exists:employees,id'],
            'action_items.*.due_date' => ['nullable', 'date'],
            'action_items.*.status' => ['nullable', 'in:open,in_progress,done'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx'],
        ]);
    }
}
