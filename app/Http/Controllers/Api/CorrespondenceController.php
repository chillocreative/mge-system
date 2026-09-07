<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AssertsSiteInProject;
use App\Http\Controllers\Controller;
use App\Models\ProjectCorrespondence;
use App\Services\CorrespondenceService;
use App\Services\CorrespondenceWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CorrespondenceController extends Controller
{
    use AssertsSiteInProject;

    public function __construct(
        private CorrespondenceService $correspondenceService,
        private CorrespondenceWorkflowService $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['project_id', 'type', 'status', 'search']);
        $perPage = min($request->integer('per_page', 15), 100);

        return $this->success($this->correspondenceService->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:project_sites,id'],
            'type' => ['required', 'exists:correspondence_types,code'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,pending,closed,declined'],
            'current_party_id' => ['nullable', 'exists:project_parties,id'],
            'raised_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'expected_close_date' => ['nullable', 'date'],
            'response' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $files = $request->file('files', []);
        unset($validated['files']);

        $this->assertSiteInProject($validated['site_id'] ?? null, $validated['project_id'] ?? null);

        $correspondence = $this->correspondenceService->create($validated, $request->user()->id, $files);
        $this->workflow->recordRaised($correspondence, $request->user()->id);

        return $this->created($correspondence, 'Correspondence created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->correspondenceService->getOne($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['sometimes', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:project_sites,id'],
            'type' => ['sometimes', 'exists:correspondence_types,code'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:open,pending,closed,declined'],
            'current_party_id' => ['nullable', 'exists:project_parties,id'],
            'raised_date' => ['sometimes', 'date'],
            'due_date' => ['nullable', 'date'],
            'expected_close_date' => ['nullable', 'date'],
            'response' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $files = $request->file('files', []);
        unset($validated['files']);

        $this->assertSiteInProject(
            $validated['site_id'] ?? null,
            $validated['project_id'] ?? ProjectCorrespondence::whereKey($id)->value('project_id'),
        );

        return $this->success($this->correspondenceService->update($id, $validated, $files), 'Correspondence updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->correspondenceService->delete($id);

        return $this->success(null, 'Correspondence deleted successfully.');
    }

    public function storeFiles(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ]);

        $correspondence = $this->correspondenceService->update($id, [], $request->file('files'));

        return $this->success($correspondence, 'Files uploaded successfully.');
    }

    public function downloadFile(int $fileId)
    {
        return $this->correspondenceService->downloadFile($fileId);
    }

    public function destroyFile(int $fileId): JsonResponse
    {
        $this->correspondenceService->deleteFile($fileId);

        return $this->success(null, 'File deleted.');
    }

    // ── Workflow (Batch 7) ──

    public function events(int $id): JsonResponse
    {
        $c = ProjectCorrespondence::findOrFail($id);

        return $this->success(
            $c->events()->with(['fromParty:id,name', 'toParty:id,name', 'creator:id,first_name,last_name'])->get(),
        );
    }

    public function handOver(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'to_party_id' => ['required', 'exists:project_parties,id'],
            'note' => ['nullable', 'string'],
        ]);
        $c = ProjectCorrespondence::findOrFail($id);

        return $this->success($this->workflow->handOver($c, $data['to_party_id'], $data['note'] ?? null, $request->user()->id), 'Handed over.');
    }

    public function note(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string']]);
        $c = ProjectCorrespondence::findOrFail($id);

        return $this->success($this->workflow->addNote($c, $data['note'], $request->user()->id), 'Note added.');
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,pending,closed,declined'],
            'note' => ['nullable', 'string'],
        ]);
        $c = ProjectCorrespondence::findOrFail($id);

        return $this->success($this->workflow->changeStatus($c, $data['status'], $data['note'] ?? null, $request->user()->id), 'Status updated.');
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'closing_reference' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);
        $c = ProjectCorrespondence::findOrFail($id);

        try {
            return $this->success($this->workflow->close($c, $data['closing_reference'], $data['note'] ?? null, $request->user()->id), 'Correspondence closed.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $c = ProjectCorrespondence::findOrFail($id);

        return $this->success($this->workflow->reopen($c, $request->input('note'), $request->user()->id), 'Correspondence reopened.');
    }

    public function pdf(int $id)
    {
        $c = ProjectCorrespondence::with([
            'project:id,name,code', 'creator:id,first_name,last_name', 'currentParty:id,name',
            'closer:id,first_name,last_name', 'files',
            'events' => fn ($q) => $q->with(['fromParty:id,name', 'toParty:id,name', 'creator:id,first_name,last_name']),
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.correspondence', ['c' => $c]);

        return $pdf->download('correspondence-'.($c->reference_no ?: $c->id).'.pdf');
    }
}
