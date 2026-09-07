<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AssertsSiteInProject;
use App\Http\Controllers\Controller;
use App\Models\WorkPermit;
use App\Services\FileUploadService;
use App\Services\Safety\WorkPermitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WorkPermitController extends Controller
{
    use AssertsSiteInProject;

    public function __construct(
        private readonly WorkPermitService $permits,
        private readonly FileUploadService $files,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = WorkPermit::with(['project:id,name,code', 'site:id,name', 'requester:id,first_name,last_name', 'approver:id,first_name,last_name'])
            ->orderByDesc('created_at');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return $this->success($query->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(
            WorkPermit::with([
                'project:id,name,code', 'site:id,name', 'requester:id,first_name,last_name',
                'approver:id,first_name,last_name', 'closer:id,first_name,last_name', 'attachments',
            ])->findOrFail($id),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $this->assertSiteInProject($data['site_id'] ?? null, $data['project_id'] ?? null);
        $submit = $request->boolean('submit');

        $permit = $this->permits->create($data, $request->user()->id, $submit);

        return $this->created($permit->fresh(), 'Permit created.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $permit = WorkPermit::findOrFail($id);
        $data = $this->validatePayload($request, true);
        $this->assertSiteInProject($data['site_id'] ?? null, $data['project_id'] ?? $permit->project_id);

        return $this->guard(fn () => $this->success($this->permits->update($permit, $data)->fresh(), 'Permit updated.'));
    }

    public function submit(int $id): JsonResponse
    {
        $permit = WorkPermit::findOrFail($id);

        return $this->guard(fn () => $this->success($this->permits->submit($permit)->fresh(), 'Permit submitted for approval.'));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $permit = WorkPermit::findOrFail($id);
        $notes = $request->input('decision_notes');

        return $this->guard(fn () => $this->success($this->permits->approve($permit, $request->user()->id, $notes)->fresh(), 'Permit approved.'));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $permit = WorkPermit::findOrFail($id);
        $notes = $request->input('decision_notes');

        return $this->guard(fn () => $this->success($this->permits->reject($permit, $request->user()->id, $notes)->fresh(), 'Permit rejected.'));
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $permit = WorkPermit::findOrFail($id);

        return $this->guard(fn () => $this->success($this->permits->close($permit, $request->user()->id)->fresh(), 'Permit closed.'));
    }

    public function upload(Request $request, int $id): JsonResponse
    {
        $permit = WorkPermit::findOrFail($id);
        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:20480']]);

        foreach ($request->file('files', []) as $file) {
            $this->files->attach($file, $permit, $request->user()->id, [
                'directory' => 'safety/permits',
                'max_size_kb' => 20480,
            ]);
        }

        return $this->success($permit->fresh()->load('attachments'), 'Files attached.');
    }

    /**
     * Turn a state-machine RuntimeException into a clean 422 instead of a 500 —
     * these are user-facing "you can't do that from here" messages.
     */
    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rule = fn (array $r) => $partial ? array_merge(['sometimes'], $r) : $r;

        return $request->validate([
            'title' => $rule(['required', 'string', 'max:255']),
            'type' => ['nullable', 'in:hot_work,confined_space,working_at_height,electrical,excavation,lifting,general'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:project_sites,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'precautions' => ['nullable', 'string'],
            'valid_from' => $rule(['required', 'date']),
            'valid_to' => $rule(['required', 'date', 'after_or_equal:valid_from']),
        ]);
    }
}
